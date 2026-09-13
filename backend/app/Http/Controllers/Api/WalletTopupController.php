<?php

namespace App\Http\Controllers\Api;

use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Admin: review customer top-up requests.
class WalletTopupController extends BaseApiController
{
    public function __construct(private WalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $query = WalletTopup::with(['user:id,name,mobile_number', 'collector:id,name', 'reviewer:id,name']);

        foreach (['status', 'method'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('reference_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%")));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
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
