import { createElement } from 'react'
import Badge from '../components/ui/Badge'

export const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
  confirmed: 'مؤكد',
  shipped: 'تم الشحن',
  ordered: 'تم الطلب',
  received: 'مستلم',
  preparing: 'قيد التجهيز',
  out_for_delivery: 'في الطريق',
  cancellation_requested: 'طلب إلغاء',
  draft: 'مسودة',
}

// Statuses an order can be set to (matches App\Enums\OrderStatus).
export const orderStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'received', 'cancellation_requested', 'cancelled']

export const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
  confirmed: '#2563eb',
  shipped: '#9333ea',
  preparing: '#0f766e',
  out_for_delivery: '#9333ea',
  received: '#16a34a',
  cancellation_requested: '#dc2626',
  draft: '#64748b',
}

export function statusVariant(status) {
  if (!status) return 'default'
  const s = String(status).toLowerCase()
  if (['completed', 'delivered', 'received', 'paid'].includes(s)) return 'success'
  if (['cancelled', 'failed', 'cancellation_requested'].includes(s)) return 'danger'
  if (['pending', 'processing', 'confirmed', 'shipped', 'ordered', 'preparing', 'out_for_delivery', 'draft'].includes(s)) return 'warning'
  return 'default'
}

export function StatusBadge({ status }) {
  return createElement(
    Badge,
    { variant: statusVariant(status) },
    statusLabels[status] || status || '-'
  )
}
