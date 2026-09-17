<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\DelegateProfile;
use App\Models\DelegateSettlement;
use App\Services\CustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Admin: delegates' cash custody, settlements (تسكير) and corrections.
class CustodyController extends BaseApiController
{
    public function __construct(private CustodyService $custody) {}

    private function delegate(int $id): AppUser
    {
        return AppUser::with('delegateProfile')
            ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = AppUser::query()
            ->select('users.id', 'users.name', 'users.mobile_number', 'users.is_active')
            ->with('delegateProfile:id,user_id,custody_balance,is_available')
            ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))
            ->addSelect([
                'custody_balance' => DelegateProfile::select('custody_balance')->whereColumn('user_id', 'users.id')->limit(1),
                'last_settlement_at' => DelegateSettlement::select('created_at')->whereColumn('delegate_id', 'users.id')->latest('id')->limit(1),
            ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%"));
        }

        if ($request->boolean('with_balance')) {
            $query->whereHas('delegateProfile', fn ($q) => $q->where('custody_balance', '>', 0));
        }

        return $this->paginated(
            $query->orderByDesc('custody_balance')->paginate($request->integer('per_page', 15)),
            meta: ['summary' => ['total_custody' => round((float) DelegateProfile::sum('custody_balance'), 2)]]
        );
    }

    public function show(int $delegate): JsonResponse
    {
        $user = $this->delegate($delegate);
        $last = DelegateSettlement::where('delegate_id', $user->id)->latest('id')->first();
        // Entries after the settlement's own ledger row (ids are ordered; timestamps can tie).
        $settlementEntryId = $last
            ? (int) CustodyEntry::where('reference_type', $last->getMorphClass())->where('reference_id', $last->id)->value('id')
            : 0;
        $unsettled = CustodyEntry::where('delegate_id', $user->id)
            ->where('amount', '>', 0)
            ->where('id', '>', $settlementEntryId);

        return $this->jsonResponse([
            'delegate' => $user->only(['id', 'name', 'mobile_number', 'email', 'is_active']),
            'balance' => $user->delegateProfile?->custody_balance ?? '0.00',
            'since_last_settlement' => [
                'collections' => (clone $unsettled)->count(),
                'amount' => round((float) (clone $unsettled)->sum('amount'), 2),
            ],
            'last_settlement' => $last?->load('receiver:id,name'),
            'settlements' => DelegateSettlement::with('receiver:id,name')->where('delegate_id', $user->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function entries(Request $request, int $delegate): JsonResponse
    {
        $user = $this->delegate($delegate);
        $query = CustodyEntry::with('createdBy:id,name')->where('delegate_id', $user->id)->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function settle(Request $request, int $delegate): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $settlement = $this->custody->settle($this->delegate($delegate), (float) $data['amount'], auth()->user(), $data['note'] ?? null);

        return $this->jsonResponse(['data' => $settlement->load(['delegate:id,name', 'receiver:id,name']), 'message' => 'تم تسجيل استلام العهدة'], 201);
    }

    public function adjust(Request $request, int $delegate): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $entry = $this->custody->adjust($this->delegate($delegate), (float) $data['amount'], $data['note']);

        return $this->jsonResponse($entry);
    }
}
