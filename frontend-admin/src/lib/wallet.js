export const walletTransactionTypes = {
  topup: { label: 'شحن', variant: 'success' },
  payment: { label: 'دفع طلب', variant: 'warning' },
  refund: { label: 'استرجاع', variant: 'info' },
  adjustment: { label: 'تعديل إداري', variant: 'default' },
}

export const topupMethods = {
  bank_transfer: 'تحويل بنكي',
  delegate_cash: 'نقداً عبر مندوب',
  gateway: 'دفع إلكتروني',
}

export const topupStatuses = {
  pending: { label: 'قيد المراجعة', variant: 'warning' },
  approved: { label: 'مقبول', variant: 'success' },
  rejected: { label: 'مرفوض', variant: 'danger' },
  cancelled: { label: 'ملغي', variant: 'default' },
  failed: { label: 'فشل', variant: 'danger' },
}

export function formatMoney(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export function formatDateTime(value) {
  return value ? new Date(value).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '-'
}

export const custodyEntryTypes = {
  order_collection: { label: 'تحصيل طلب', variant: 'warning' },
  wallet_collection: { label: 'شحن محفظة', variant: 'info' },
  settlement: { label: 'تسليم للمكتب', variant: 'success' },
  adjustment: { label: 'تعديل إداري', variant: 'default' },
}
