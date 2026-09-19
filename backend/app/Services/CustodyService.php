<?php

namespace App\Services;

use App\Enums\CustodyEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\DelegateProfile;
use App\Models\DelegateSettlement;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\WalletTopup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Tracks cash each delegate holds for the office (عهدة) and its hand-over (تسكير).
// The only place delegate_profiles.custody_balance changes; amounts in cents.
class CustodyService
{
    // On delivery: the order's unpaid amount is collected in cash by its delegate.
    public function collectOrderCash(Order $order): ?CustodyEntry
    {
        if (! $order->delegate_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $paidCents = $this->cents((float) $order->payments()
                ->where('status', PaymentStatus::Paid->value)
                ->lockForUpdate()
                ->sum('amount'));
            $outstandingCents = $this->cents((float) $order->total_amount) - $paidCents;

            if ($outstandingCents <= 0) {
                return null;
            }

            $order->payments()->create([
                'amount' => $outstandingCents / 100,
                'method' => PaymentMethod::Cash,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'collected_by' => $order->delegate_id,
            ]);

            return $this->record($order->delegate_id, $outstandingCents, CustodyEntryType::OrderCollection, $order, "تحصيل الطلب {$order->order_number}");
        });
    }

    public function recordWalletCollection(WalletTopup $topup): CustodyEntry
    {
        return $this->record(
            $topup->collected_by,
            $this->cents((float) $topup->amount),
            CustodyEntryType::WalletCollection,
            $topup,
            'شحن محفظة '.($topup->user?->name ?? '')
        );
    }

    // The delegate hands cash to the office; cannot exceed what they hold.
    public function settle(AppUser $delegate, float $amount, AppUser $receiver, ?string $note = null): DelegateSettlement
    {
        $cents = $this->cents($amount);
        if ($cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون أكبر من صفر']);
        }

        return DB::transaction(function () use ($delegate, $cents, $receiver, $note) {
            $profile = $this->lockProfile($delegate->id);
            $beforeCents = $this->cents((float) $profile->custody_balance);

            if ($cents > $beforeCents) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من العهدة الحالية ('.number_format($beforeCents / 100, 2).' د.ل)']);
            }

            $settlement = DelegateSettlement::create([
                'delegate_id' => $delegate->id,
                'amount' => $cents / 100,
                'custody_before' => $beforeCents / 100,
                'custody_after' => ($beforeCents - $cents) / 100,
                'received_by' => $receiver->id,
                'note' => $note,
            ]);

            $this->record($delegate->id, -$cents, CustodyEntryType::Settlement, $settlement, $note ?: "تسليم عهدة {$settlement->reference_number}");

            return $settlement;
        });
    }

    // A delegate hands a customer their cash refund out of the cash they hold.
    public function payRefund(AppUser $delegate, OrderReturn $return): CustodyEntry
    {
        return $this->record(
            $delegate->id,
            -$this->cents((float) $return->refund_amount),
            CustodyEntryType::RefundPayout,
            $return,
            'استرداد نقدي لمرتجع الطلب '.($return->order?->order_number ?? ''),
        );
    }

    // Admin correction of a delegate's custody (e.g. shortage write-off), never below zero.
    public function adjust(AppUser $delegate, float $signedAmount, string $note): CustodyEntry
    {
        $cents = $this->cents($signedAmount);
        if ($cents === 0) {
            throw ValidationException::withMessages(['amount' => 'المبلغ لا يمكن أن يكون صفراً']);
        }

        return $this->record($delegate->id, $cents, CustodyEntryType::Adjustment, null, $note);
    }

    private function record(int $delegateId, int $changeCents, CustodyEntryType $type, ?Model $reference, ?string $note): CustodyEntry
    {
        return DB::transaction(function () use ($delegateId, $changeCents, $type, $reference, $note) {
            $profile = $this->lockProfile($delegateId);
            $newCents = $this->cents((float) $profile->custody_balance) + $changeCents;

            if ($newCents < 0) {
                throw ValidationException::withMessages(['amount' => 'العهدة لا يمكن أن تكون بالسالب']);
            }

            $profile->update(['custody_balance' => $newCents / 100]);

            return CustodyEntry::create([
                'delegate_id' => $delegateId,
                'type' => $type,
                'amount' => $changeCents / 100,
                'balance_after' => $newCents / 100,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => auth()->id(),
            ]);
        });
    }

    private function lockProfile(int $delegateId): DelegateProfile
    {
        DelegateProfile::firstOrCreate(['user_id' => $delegateId]);

        return DelegateProfile::where('user_id', $delegateId)->lockForUpdate()->firstOrFail();
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
