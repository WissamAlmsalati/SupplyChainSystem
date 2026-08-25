import { useEffect, useState } from 'react'
import client from '../api/client'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
}

const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
}

const availableStatuses = ['pending', 'processing', 'completed', 'delivered', 'cancelled', 'failed']

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Orders() {
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
      const res = await client.get(`/orders?page=${page}`)
      setOrders(res.data.data)
      setLastPage(res.data.last_page)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الطلبات')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [page])

  const updateStatus = async (order, status) => {
    setUpdating(order.id)
    try {
      await client.put(`/orders/${order.id}`, { status })
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الحالة')
    } finally {
      setUpdating(null)
    }
  }

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">طلباتي</h1>
        <p className="mt-1 text-muted">إدارة طلبات فروع مقهاك</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-background text-muted">
            <tr>
              <th className="px-4 py-3 text-start">#</th>
              <th className="px-4 py-3 text-start">العميل</th>
              <th className="px-4 py-3 text-start">الفرع</th>
              <th className="px-4 py-3 text-start">الحالة</th>
              <th className="px-4 py-3 text-end">الإجمالي</th>
              <th className="px-4 py-3 text-start">التاريخ</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">جاري التحميل...</td></tr>
            ) : orders.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">لا توجد طلبات.</td></tr>
            ) : (
              orders.map((o) => (
                <tr key={o.id} className="hover:bg-background/50">
                  <td className="px-4 py-3">{o.id}</td>
                  <td className="px-4 py-3">{o.user?.name ?? '-'}</td>
                  <td className="px-4 py-3">{o.branch?.name ?? '-'}</td>
                  <td className="px-4 py-3">
                    <select
                      value={o.status}
                      disabled={updating === o.id}
                      onChange={(e) => updateStatus(o, e.target.value)}
                      className="rounded-md border border-border-strong bg-background px-2 py-1 text-xs"
                    >
                      {availableStatuses.map((s) => (
                        <option key={s} value={s}>{statusLabels[s]}</option>
                      ))}
                    </select>
                  </td>
                  <td className="px-4 py-3 text-end font-medium">{formatMoney(o.total_amount)} د.ل</td>
                  <td className="px-4 py-3 text-muted">
                    {o.order_date ? new Date(o.order_date).toLocaleDateString('en-US') : '-'}
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
