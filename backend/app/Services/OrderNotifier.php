<?php

namespace App\Services;

use App\Enums\DeliveryFailureReason;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;

/**
 * Tells the people waiting on an order what just happened to it.
 *
 * A cafe used to learn that its order was confirmed, on the road or cancelled
 * only by opening the order again or phoning the office, and a driver learned
 * of a new job by looking. Called from the Order model's hooks, so it covers
 * every path that changes an order: dashboard, driver app, customer app.
 *
 * Nobody is told about their own action: the person who made the change
 * already knows.
 */
class OrderNotifier
{
    public function statusChanged(Order $order, OrderStatus $from): void
    {
        $number = $order->order_number;
        $actor = auth()->id();

        [$title, $message] = match ($order->status) {
            OrderStatus::Confirmed => ['تم تأكيد طلبك', "طلبك {$number} مؤكد وسنبدأ تجهيزه"],
            OrderStatus::Preparing => ['طلبك قيد التجهيز', "نجهّز الآن طلبك {$number}"],
            OrderStatus::OutForDelivery => ['طلبك في الطريق', $this->onTheWay($order)],
            OrderStatus::Delivered => ['تم توصيل طلبك', "وصل طلبك {$number}. أكّد الاستلام من التطبيق"],
            OrderStatus::DeliveryFailed => ['تعذّر توصيل طلبك', "لم نتمكن من تسليم طلبك {$number}: {$this->failure($order)}. سنتواصل معك لإعادة المحاولة"],
            OrderStatus::Cancelled => ['أُلغي طلبك', "أُلغي طلبك {$number}"],
            // Back to pending from a cancellation request means the office said no.
            OrderStatus::Pending => $from === OrderStatus::CancellationRequested
                ? ['رُفض طلب الإلغاء', "لم يُقبل إلغاء طلبك {$number} وسيستمر تنفيذه"]
                : [null, null],
            default => [null, null],
        };

        if ($title !== null && $order->user_id !== $actor) {
            Notification::sendTo([$order->user_id], $title, $message, "/orders/{$order->id}", 'order', ['order', $order->id]);
        }

        // The office hears about what it has to act on.
        if ($order->status === OrderStatus::DeliveryFailed) {
            Notification::notifyAdmins('تعذّر توصيل طلب', "الطلب {$number}: {$this->failure($order)}. المحاولة رقم {$order->delivery_attempts}", "/orders/{$order->id}", 'order');
        }
        if ($order->status === OrderStatus::Received) {
            Notification::notifyAdmins('أكّد الزبون الاستلام', "الطلب {$number}", "/orders/{$order->id}", 'order');
        }

        // A driver whose job was cancelled under them should not drive to it.
        if ($order->status === OrderStatus::Cancelled && $order->delegate_id && $order->delegate_id !== $actor
            && in_array($from, [OrderStatus::OutForDelivery, OrderStatus::DeliveryFailed, OrderStatus::Preparing, OrderStatus::Confirmed], true)) {
            Notification::sendTo([$order->delegate_id], 'أُلغي طلب مسند إليك', "الطلب {$number} أُلغي، لا توصّله", "/orders/{$order->id}", 'order', ['order', $order->id]);
        }
    }

    public function delegateAssigned(Order $order): void
    {
        if ($order->delegate_id === auth()->id()) {
            return;
        }

        $where = trim(($order->delivery_address_name ?? '').'، '.($order->delivery_city ?? ''), '، ');
        Notification::sendTo(
            [$order->delegate_id],
            'طلب جديد مسند إليك',
            "الطلب {$order->order_number}".($where !== '' ? " إلى {$where}" : ''),
            "/orders/{$order->id}",
            'order',
            ['order', $order->id],
        );
    }

    private function onTheWay(Order $order): string
    {
        $driver = $order->delegate;

        return "طلبك {$order->order_number} خرج للتوصيل".($driver ? " مع {$driver->name}".($driver->mobile_number ? " ({$driver->mobile_number})" : '') : '');
    }

    private function failure(Order $order): string
    {
        $reason = DeliveryFailureReason::tryFrom((string) $order->delivery_failure_reason);

        return $reason === DeliveryFailureReason::Other || $reason === null
            ? ($order->delivery_failure_note ?: 'سبب غير محدد')
            : $reason->label();
    }
}
