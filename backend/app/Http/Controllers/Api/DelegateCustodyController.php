<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\CustodyEntry;
use App\Models\DelegateSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Delegate app: the cash I hold for the office and my hand-overs.
class DelegateCustodyController extends BaseApiController
{
    private function isDelegate(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Delegate->value;
    }

    /**
     * @OA\Get(path="/delegate/custody", tags={"Delegate Mobile"}, summary="My cash custody balance and ledger", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"order_collection","wallet_collection","settlement","adjustment"})),
     *     @OA\Response(response=200, description="balance, since_last_settlement, last_settlement, entries (paginated)"))
     */
    public function show(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $delegateId = auth()->id();
        $last = DelegateSettlement::where('delegate_id', $delegateId)->latest('id')->first();

        $entries = CustodyEntry::where('delegate_id', $delegateId)->orderByDesc('id');
        if ($request->filled('type')) {
            $entries->where('type', $request->input('type'));
        }

        $sinceLast = CustodyEntry::where('delegate_id', $delegateId)
            ->where('amount', '>', 0)
            ->where('id', '>', $this->settlementEntryId($last));

        return $this->jsonResponse([
            'balance' => auth()->user()->delegateProfile?->custody_balance ?? '0.00',
            'since_last_settlement' => [
                'collections' => (clone $sinceLast)->count(),
                'amount' => round((float) (clone $sinceLast)->sum('amount'), 2),
            ],
            'last_settlement' => $last,
            'entries' => $entries->paginate($request->integer('per_page', 20)),
        ]);
    }

    private function settlementEntryId(?DelegateSettlement $settlement): int
    {
        return $settlement
            ? (int) CustodyEntry::where('reference_type', $settlement->getMorphClass())->where('reference_id', $settlement->id)->value('id')
            : 0;
    }

    /**
     * @OA\Get(path="/delegate/custody/settlements", tags={"Delegate Mobile"}, summary="My cash hand-overs to the office", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Paginated settlements"))
     */
    public function settlements(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        return $this->jsonResponse(
            DelegateSettlement::with('receiver:id,name')->where('delegate_id', auth()->id())->orderByDesc('id')->paginate($request->integer('per_page', 15))
        );
    }
}
