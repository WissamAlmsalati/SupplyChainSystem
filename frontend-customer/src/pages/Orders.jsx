import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

const statusLabels = {
  pending: 'قيد الانتظار',
  confirmed: 'مؤكد',
  preparing: 'قيد التجهيز',
  out_for_delivery: 'في الطريق',
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
  received: '#15803d',
  cancellation_requested: '#9333ea',
  cancelled: '#dc2626',
  failed: '#dc2626',
  confirmed: '#2563eb',
  shipped: '#9333ea',
}

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Orders() {
  const navigate = useNavigate()
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [updating, setUpdating] = useState(null)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/customer/orders?page=${page}`)
      setOrders(res.data.data ?? [])
      setLastPage(res.data.meta?.last_page ?? res.data.last_page ?? 1)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الطلبات')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [page])

  const confirmReceipt = async (order) => {
    setUpdating(order.id)
    try {
      await client.put(`/customer/orders/${order.id}/status`, { status: 'received' })
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تأكيد الاستلام')
    } finally {
      setUpdating(null)
    }
  }

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">طلباتي</h1>
          <p className="mt-1 text-muted">طلبات فروع مقهاك فقط</p>
        </div>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-background text-muted">
            <tr>
              <th className="px-4 py-3 text-start">#</th>
              <th className="px-4 py-3 text-start">رقم الطلب</th>
              <th className="px-4 py-3 text-start">العنوان</th>
              <th className="px-4 py-3 text-start">الحالة</th>
              <th className="px-4 py-3 text-end">الإجمالي</th>
              <th className="px-4 py-3 text-start">التاريخ</th>
              <th className="px-4 py-3 text-start">إجراء</th>
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
                  <td className="px-4 py-3"><div className="h-4 w-16 animate-pulse rounded-md bg-border" /></td>
                </tr>
              ))
            ) : orders.length === 0 ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">لا توجد طلبات.</td></tr>
            ) : (
              orders.map((o) => (
                <tr
                  key={o.id}
                  onClick={() => navigate(`/orders/${o.id}`)}
                  className="cursor-pointer hover:bg-background/50"
                >
                  <td className="px-4 py-3">{o.id}</td>
                  <td className="px-4 py-3 font-medium">{o.order_number ?? `#${o.id}`}</td>
                  <td className="px-4 py-3">{o.delivery_address_name ?? '-'}</td>
                  <td className="px-4 py-3">
                    <span
                      className="rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                      style={{ background: statusColors[o.status] || '#78716c' }}
                    >
                      {statusLabels[o.status] || o.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-end font-medium">{formatMoney(o.total_amount)} د.ل</td>
                  <td className="px-4 py-3 text-muted">
                    {o.placed_at ? new Date(o.placed_at).toLocaleDateString('en-US') : '-'}
                  </td>
                  <td className="px-4 py-3">
                    {o.status === 'delivered' && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          confirmReceipt(o)
                        }}
                        disabled={updating === o.id}
                        className="rounded-md bg-primary px-3 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                      >
                        {updating === o.id ? '...' : 'تأكيد الاستلام'}
                      </button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {lastPage > 1 && (
        <div className="mt-4 flex items-center justify-center gap-2">
          {Array.from({ length: lastPage }, (_, i) => i + 1).map((p) => (
            <button
              key={p}
              onClick={() => setPage(p)}
              className={`h-8 w-8 rounded-md text-sm font-medium ${
                p === page ? 'bg-primary text-white' : 'border border-border bg-surface text-foreground hover:bg-background'
              }`}
            >
              {p}
            </button>
          ))}
        </div>
      )}
    </>
  )
}
