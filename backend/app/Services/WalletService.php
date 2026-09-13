<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TopupMethod;
use App\Enums\TopupStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\AppUser;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// The only place wallet balances change. Amounts are handled in cents to avoid
// float drift; every change locks the wallet row and writes a ledger entry.
class WalletService
{
    public function walletFor(AppUser $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    public function credit(Wallet $wallet, float $amount, WalletTransactionType $type, ?Model $reference = null, ?string $note = null): WalletTransaction
    {
        return $this->apply($wallet, $this->cents($amount), $type, $reference, $note);
    }

    public function debit(Wallet $wallet, float $amount, WalletTransactionType $type, ?Model $reference = null, ?string $note = null): WalletTransaction
    {
        return $this->apply($wallet, -$this->cents($amount), $type, $reference, $note);
    }

    // Admin correction; positive adds, negative removes (never below zero).
    public function adjust(Wallet $wallet, float $signedAmount, string $note): WalletTransaction
    {
        if ($this->cents($signedAmount) === 0) {
            throw ValidationException::withMessages(['amount' => 'المبلغ لا يمكن أن يكون صفراً']);
        }

        return $this->apply($wallet, $this->cents($signedAmount), WalletTransactionType::Adjustment, null, $note);
    }

    public function requestTopup(AppUser $user, float $amount, TopupMethod $method, array $attributes = []): WalletTopup
    {
        $this->assertTopupAmount($amount);

        $topup = WalletTopup::create($attributes + [
            'wallet_id' => $this->walletFor($user)->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $method,
            'status' => TopupStatus::Pending,
        ]);

        if ($method !== TopupMethod::Gateway) {
            Notification::notifyAdmins(
                'طلب شحن محفظة',
                "{$user->name} طلب شحن " . number_format($amount, 2) . ' د.ل',
                "/wallet-topups/{$topup->id}",
                'wallet'
            );
        }

        return $topup;
    }

    // Credits a pending top-up exactly once (admin approval or gateway success).
    public function approveTopup(WalletTopup $topup, ?AppUser $reviewer = null, ?string $gatewayReference = null): WalletTopup
    {
        $topup = DB::transaction(function () use ($topup, $reviewer, $gatewayReference) {
            $locked = WalletTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $locked->update([
                'status' => TopupStatus::Approved,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'gateway_reference' => $gatewayReference ?? $locked->gateway_reference,
            ]);

            $this->credit($locked->wallet, (float) $locked->amount, WalletTransactionType::TopUp, $locked, $this->topupNote($locked));

            return $locked;
        });

        $this->notifyCustomer($topup->user_id, 'تم شحن محفظتك', 'أُضيف ' . number_format((float) $topup->amount, 2) . ' د.ل إلى رصيدك');

        return $topup;
    }

    public function rejectTopup(WalletTopup $topup, AppUser $reviewer, string $reason): WalletTopup
    {
        $topup = $this->closeTopup($topup, TopupStatus::Rejected, [
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifyCustomer($topup->user_id, 'تم رفض طلب الشحن', $reason);

        return $topup;
    }

    public function cancelTopup(WalletTopup $topup): WalletTopup
    {
        return $this->closeTopup($topup, TopupStatus::Cancelled);
    }

    public function failTopup(WalletTopup $topup, ?string $gatewayReference = null): WalletTopup
    {
        return $this->closeTopup($topup, TopupStatus::Failed, ['gateway_reference' => $gatewayReference]);
    }

    // Cash handed to a delegate is credited immediately and kept for reconciliation.
    public function collectCash(AppUser $delegate, AppUser $customer, float $amount, ?Order $order = null, ?string $note = null): WalletTopup
    {
        $this->assertTopupAmount($amount);

        $topup = DB::transaction(function () use ($delegate, $customer, $amount, $order, $note) {
            $topup = WalletTopup::create([
                'wallet_id' => $this->walletFor($customer)->id,
                'user_id' => $customer->id,
                'amount' => $amount,
                'method' => TopupMethod::DelegateCash,
                'status' => TopupStatus::Pending,
                'collected_by' => $delegate->id,
                'order_id' => $order?->id,
                'note' => $note,
            ]);

            $topup = $this->approveTopup($topup, $delegate);
            app(CustodyService::class)->recordWalletCollection($topup);

            return $topup;
        });

        Notification::notifyAdmins(
            'تحصيل نقدي من مندوب',
            "{$delegate->name} حصّل " . number_format($amount, 2) . " د.ل من {$customer->name}",
            "/wallet-topups/{$topup->id}",
            'wallet'
        );

        return $topup;
    }

    // Pays the whole order total from the wallet. Call inside the order transaction
    // so an insufficient balance rolls back the order and its stock deduction.
    public function payOrder(Order $order, AppUser $customer): void
    {
        $total = (float) $order->total_amount;

        $this->debit($this->walletFor($customer), $total, WalletTransactionType::Payment, $order, "دفع الطلب {$order->order_number}");

        $order->payments()->create([
            'amount' => $total,
            'method' => PaymentMethod::Wallet,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    // Returns wallet payments of a cancelled order to the wallet (once per payment).
    public function refundOrder(Order $order): void
    {
        if ($order->status !== OrderStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($order) {
            $payments = $order->payments()
                // Wallet payments, and cash already collected by the delegate (who keeps it in custody).
                ->whereIn('method', [PaymentMethod::Wallet->value, PaymentMethod::Cash->value])
                ->where('status', PaymentStatus::Paid->value)
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $payment->update(['status' => PaymentStatus::Refunded]);
                $this->credit($this->walletFor($order->user), (float) $payment->amount, WalletTransactionType::Refund, $order, "استرجاع الطلب {$order->order_number}");
            }

            if ($payments->isNotEmpty()) {
                $this->notifyCustomer($order->user_id, 'تم استرجاع مبلغ الطلب', "أُعيد " . number_format((float) $payments->sum('amount'), 2) . " د.ل إلى محفظتك بعد إلغاء الطلب {$order->order_number}");
            }
        });
    }

    public function balance(AppUser $user): float
    {
        return (float) ($user->wallet?->balance ?? 0);
    }

    private function apply(Wallet $wallet, int $changeCents, WalletTransactionType $type, ?Model $reference, ?string $note): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $changeCents, $type, $reference, $note) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $currentCents = $this->cents((float) $locked->balance);

            if ($changeCents < 0 && ! $locked->is_active) {
                throw ValidationException::withMessages(['wallet' => 'المحفظة موقوفة']);
            }

            if ($currentCents + $changeCents < 0) {
                throw new InsufficientWalletBalanceException($currentCents / 100, -$changeCents / 100);
            }

            $newBalance = ($currentCents + $changeCents) / 100;
            $locked->update(['balance' => $newBalance]);
            $wallet->setRawAttributes($locked->getAttributes(), true);

            return WalletTransaction::create([
                'wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $changeCents / 100,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => auth()->id(),
            ]);
        });
    }

    private function closeTopup(WalletTopup $topup, TopupStatus $status, array $attributes = []): WalletTopup
    {
        return DB::transaction(function () use ($topup, $status, $attributes) {
            $locked = WalletTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $locked->update(['status' => $status] + $attributes);

            return $locked;
        });
    }

    private function assertPending(WalletTopup $topup): void
    {
        if ($topup->status !== TopupStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'تمت معالجة طلب الشحن مسبقاً']);
        }
    }

    private function assertTopupAmount(float $amount): void
    {
        $min = (float) config('wallet.min_topup');
        $max = (float) config('wallet.max_topup');

        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages(['amount' => "مبلغ الشحن يجب أن يكون بين {$min} و {$max} د.ل"]);
        }
    }

    private function topupNote(WalletTopup $topup): string
    {
        return match ($topup->method) {
            TopupMethod::BankTransfer => 'شحن بتحويل بنكي' . ($topup->reference_number ? " ({$topup->reference_number})" : ''),
            TopupMethod::DelegateCash => 'تحصيل نقدي عن طريق مندوب',
            TopupMethod::Gateway => 'شحن إلكتروني' . ($topup->gateway_reference ? " ({$topup->gateway_reference})" : ''),
        };
    }

    private function notifyCustomer(int $userId, string $title, string $message): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => 'wallet',
            'title' => $title,
            'message' => $message,
            'link' => '/wallet',
        ]);
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
