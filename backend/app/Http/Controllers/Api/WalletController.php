<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustodyEntryType;
use App\Enums\TopupMethod;
use App\Enums\TopupStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\DelegateProfile;
use App\Models\DelegateSettlement;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Admin: customer wallets, their ledgers and manual adjustments.
class WalletController extends BaseApiController
{
    public function __construct(private WalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $query = Wallet::with('user:id,name,mobile_number,email')
            ->withCount(['topups as pending_topups_count' => fn ($q) => $q->where('status', 'pending')]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%"));
        }

        if ($request->filled('has_balance')) {
            $request->boolean('has_balance') ? $query->where('balance', '>', 0) : $query->where('balance', 0);
        }

        $summary = [
            'total_balance' => round((float) Wallet::sum('balance'), 2),
            'wallets' => Wallet::count(),
        ];

        return $this->paginated(
            $query->orderByDesc('balance')->paginate($request->integer('per_page', 15)),
            meta: ['summary' => $summary]
        );
    }

    /**
     * Liquidity overview: money held in customer wallets (owed to customers), cash
     * held by delegates (owed to the office), pending top-ups and period flows.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = in_array($request->input('period'), ['today', 'week', 'month', 'all'], true) ? $request->input('period') : 'month';
        $from = match ($period) {
            'today' => Carbon::now()->startOfDay(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'all' => null,
        };
        $inPeriod = fn ($query, string $column = 'created_at') => $from ? $query->where($column, '>=', $from) : $query;
        $sum = fn ($query) => round((float) $query->sum('amount'), 2);

        $walletsTotal = round((float) Wallet::sum('balance'), 2);
        $custodyTotal = round((float) DelegateProfile::sum('custody_balance'), 2);

        $approved = $inPeriod(WalletTopup::where('status', TopupStatus::Approved->value), 'reviewed_at');
        $topupsByMethod = collect(TopupMethod::cases())->mapWithKeys(fn (TopupMethod $m) => [
            $m->value => $sum((clone $approved)->where('method', $m->value)),
        ]);

        $tx = fn (WalletTransactionType $type) => $inPeriod(WalletTransaction::where('type', $type->value));
        $custody = fn (CustodyEntryType $type) => $inPeriod(CustodyEntry::where('type', $type->value));
        $pending = WalletTopup::where('status', TopupStatus::Pending->value)->where('method', '!=', TopupMethod::Gateway->value);

        return $this->jsonResponse([
            'period' => $period,
            'from' => $from?->toIso8601String(),
            'liquidity' => [
                'customer_wallets' => $walletsTotal,
                'delegate_custody' => $custodyTotal,
                'total' => round($walletsTotal + $custodyTotal, 2),
            ],
            'wallets' => [
                'count' => Wallet::count(),
                'with_balance' => Wallet::where('balance', '>', 0)->count(),
            ],
            'delegates_with_custody' => DelegateProfile::where('custody_balance', '>', 0)->count(),
            'pending_topups' => [
                'count' => (clone $pending)->count(),
                'amount' => $sum(clone $pending),
            ],
            'flows' => [
                'topups_by_method' => $topupsByMethod,
                'topups_total' => round($topupsByMethod->sum(), 2),
                'wallet_payments' => abs($sum($tx(WalletTransactionType::Payment))),
                'wallet_refunds' => $sum($tx(WalletTransactionType::Refund)),
                'adjustments_in' => $sum($tx(WalletTransactionType::Adjustment)->where('amount', '>', 0)),
                'adjustments_out' => abs($sum($tx(WalletTransactionType::Adjustment)->where('amount', '<', 0))),
                'order_cash_collected' => $sum($custody(CustodyEntryType::OrderCollection)),
                'wallet_cash_collected' => $sum($custody(CustodyEntryType::WalletCollection)),
                'settlements_received' => $sum($inPeriod(DelegateSettlement::query())),
            ],
            'top_wallets' => Wallet::with('user:id,name,mobile_number')->where('balance', '>', 0)->orderByDesc('balance')->limit(5)->get(['id', 'user_id', 'balance']),
            'delegates' => AppUser::query()
                ->select('users.id', 'users.name', 'users.mobile_number')
                ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))
                ->join('delegate_profiles', 'delegate_profiles.user_id', '=', 'users.id')
                ->where('delegate_profiles.custody_balance', '>', 0)
                ->addSelect([
                    'delegate_profiles.custody_balance',
                    'last_settlement_at' => DelegateSettlement::select('created_at')->whereColumn('delegate_id', 'users.id')->latest('id')->limit(1),
                ])
                ->orderByDesc('delegate_profiles.custody_balance')
                ->get(),
        ]);
    }

    public function show(Wallet $wallet): JsonResponse
    {
        return $this->jsonResponse($wallet->load([
            'user:id,name,mobile_number,email',
            'topups' => fn ($q) => $q->with(['collector:id,name', 'reviewer:id,name'])->limit(20),
        ]));
    }

    public function transactions(Request $request, Wallet $wallet): JsonResponse
    {
        $query = $wallet->transactions()->with('createdBy:id,name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    // Positive amount credits, negative debits; a note is required for the audit trail.
    public function adjust(Request $request, Wallet $wallet): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $this->wallets->adjust($wallet, (float) $data['amount'], $data['note']);

        return $this->jsonResponse([
            'wallet' => $wallet->fresh('user:id,name,mobile_number'),
            'transaction' => $transaction,
        ]);
    }

    public function toggleActive(Wallet $wallet): JsonResponse
    {
        $wallet->update(['is_active' => ! $wallet->is_active]);

        return $this->jsonResponse($wallet->fresh('user:id,name,mobile_number'));
    }
}
