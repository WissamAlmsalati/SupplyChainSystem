import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

const statusLabels = {
  pending: 'قيد الانتظار',
  confirmed: 'مؤكد',
  preparing: 'قيد التجهيز',
  out_for_delivery: 'في الطريق',
  delivery_failed: 'تعذّر التوصيل',
  delivered: 'تم التوصيل',
  received: 'تم الاستلام',
  cancellation_requested: 'طلب إلغاء',
  cancelled: 'ملغي',
}

const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
  delivery_failed: '#dc2626',
  out_for_delivery: '#9333ea',
  confirmed: '#2563eb',
  preparing: '#0f766e',
  received: '#16a34a',
}

const availableStatuses = Object.keys(statusLabels)

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function DelegateOrders() {
  const navigate = useNavigate()
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const load = async (filter = statusFilter) => {
    setLoading(true)
    setError('')
    try {
      const params = filter ? `?status=${encodeURIComponent(filter)}` : ''
      const res = await client.get(`/delegate/orders${params}`)
      setOrders(res.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الطلبات')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [statusFilter])

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">طلباتي</h1>
        <p className="mt-1 text-muted">الطلبات المخصصة لك</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <label className="text-sm text-muted">تصفية بالحالة:</label>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-md border border-border-strong bg-background px-3 py-2 text-sm text-foreground"
        >
          <option value="">الكل</option>
          {availableStatuses.map((s) => (
            <option key={s} value={s}>{statusLabels[s]}</option>
          ))}
        </select>
      </div>

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-background text-muted">
            <tr>
              <th className="px-4 py-3 text-start">#</th>
              <th className="px-4 py-3 text-start">العميل</th>
              <th className="px-4 py-3 text-start">العنوان</th>
              <th className="px-4 py-3 text-start">الحالة</th>
              <th className="px-4 py-3 text-end">الإجمالي</th>
              <th className="px-4 py-3 text-start">التاريخ</th>
              <th className="px-4 py-3 text-start">إجراءات</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  <td className="px-4 py-3"><div className="h-4 w-10 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-28 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-24 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-6 w-24 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3 text-end"><div className="ms-auto h-4 w-20 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-20 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-6 w-12 animate-pulse rounded-md bg-border" /></td>
                </tr>
              ))
            ) : orders.length === 0 ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">لا توجد طلبات.</td></tr>
            ) : (
              orders.map((o) => (
                <tr key={o.id} className="hover:bg-background/50">
                  <td className="px-4 py-3">#{o.id}</td>
                  <td className="px-4 py-3">{o.user?.name ?? '-'}</td>
                  <td className="px-4 py-3">{o.delivery_address_name ?? '-'}</td>
                  <td className="px-4 py-3">
                    {/* ponytail: a badge, not a picker. A dropdown of every status in a list
                        row made "delivered" one slip away, and delivering books cash into the
                        driver's custody. The moves, with their confirmation, are on the order page. */}
                    <span className="rounded-full border border-border px-2.5 py-1 text-xs font-medium" style={{ color: statusColors[o.status] }}>{statusLabels[o.status] ?? o.status}</span>
                  </td>
                  <td className="px-4 py-3 text-end font-medium">{formatMoney(o.total_amount)} د.ل</td>
                  <td className="px-4 py-3 text-muted">
                    {o.placed_at ? new Date(o.placed_at).toLocaleDateString('en-US') : '-'}
                  </td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => navigate(`/orders/${o.id}`)}
                      className="rounded-md bg-primary px-3 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                    >
                      عرض
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </>
  )
}
