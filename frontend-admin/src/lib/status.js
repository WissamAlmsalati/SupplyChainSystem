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
}

export const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
  confirmed: '#2563eb',
  shipped: '#9333ea',
}

export function statusVariant(status) {
  if (!status) return 'default'
  const s = String(status).toLowerCase()
  if (['completed', 'delivered', 'received', 'paid'].includes(s)) return 'success'
  if (['cancelled', 'failed'].includes(s)) return 'danger'
  if (['pending', 'processing', 'confirmed', 'shipped', 'ordered'].includes(s)) return 'warning'
  return 'default'
}

export function StatusBadge({ status }) {
  return createElement(
    Badge,
    { variant: statusVariant(status) },
    statusLabels[status] || status || '-'
  )
}
