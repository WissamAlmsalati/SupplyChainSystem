<?php

namespace App\Http\Controllers\Api;

use App\Enums\TopupStatus;
use App\Models\WalletTopup;
use App\Services\WalletService;
use App\Support\ArabicText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Admin: review customer top-up requests.
class WalletTopupController extends BaseApiController
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @OA\Get(path="/wallet-topups", tags={"Wallets"}, summary="Top-up requests",
     *     description="meta.status_counts holds the number of rows per status under the other
     *         filters, so the dashboard can show that rejected requests exist while the
     *         pending queue is empty.",
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="method", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Paginated top-ups"))
     */
    public function index(Request $request): JsonResponse
    {
        // Everything except status; the counts below need that same scope.
        $base = WalletTopup::query()
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($inner) => ArabicText::filter($inner, $search, ['reference_number'])
                    ->orWhereHas('user', fn ($u) => ArabicText::filter($u, $search, ['name', 'mobile_number'])));
            });

        $found = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->get()
            ->mapWithKeys(fn ($r) => [$r->status instanceof \BackedEnum ? $r->status->value : $r->status => (int) $r->total]);

        $counts = collect(TopupStatus::values())->mapWithKeys(fn ($s) => [$s => $found[$s] ?? 0])->all();
        $counts['all'] = array_sum($counts);

        $query = (clone $base)
            ->with(['user:id,name,mobile_number', 'collector:id,name', 'reviewer:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        return $this->paginated(
            $query->orderByDesc('id')->paginate($request->integer('per_page', 15)),
            meta: ['status_counts' => $counts],
        );
    }

    public function show(WalletTopup $walletTopup): JsonResponse
    {
        return $this->jsonResponse($walletTopup->load(['user:id,name,mobile_number,email', 'wallet', 'collector:id,name', 'reviewer:id,name', 'order:id,order_number']));
    }

    public function approve(WalletTopup $walletTopup): JsonResponse
    {
        $topup = $this->wallets->approveTopup($walletTopup, auth()->user());

        return $this->jsonResponse($topup->load(['user:id,name,mobile_number', 'wallet', 'reviewer:id,name']));
    }

    public function reject(Request $request, WalletTopup $walletTopup): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $topup = $this->wallets->rejectTopup($walletTopup, auth()->user(), $data['reason']);

        return $this->jsonResponse($topup->load(['user:id,name,mobile_number', 'wallet', 'reviewer:id,name']));
    }
}
