import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  received: 'تم الاستلام',
  cancellation_requested: 'طلب إلغاء',
  cancelled: 'ملغي',
  failed: 'فاشل',
  confirmed: 'مؤكد',
  shipped: 'تم الشحن',
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

export default function CafeOrderDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [confirming, setConfirming] = useState(false)

  useEffect(() => {
    async function load() {
      setLoading(true)
      try {
        const res = await client.get(`/cafe/orders/${id}`)
        setOrder(res.data?.data ?? res.data)
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل الطلب')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const confirmReceipt = async () => {
    setConfirming(true)
    try {
      await client.put(`/cafe/orders/${id}/status`, { status: 'received' })
      const res = await client.get(`/cafe/orders/${id}`)
      setOrder(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تأكيد الاستلام')
    } finally {
      setConfirming(false)
    }
  }

  if (loading) {
    return (
      <div className="pt-6">
        <div className="h-8 w-48 animate-pulse rounded-md bg-border" />
        <div className="mt-6 grid gap-4">
          <div className="h-32 animate-pulse rounded-xl bg-border" />
          <div className="h-48 animate-pulse rounded-xl bg-border" />
        </div>
      </div>
    )
  }

  if (!order) {
    return <div className="pt-6 text-danger">{error || 'الطلب غير موجود.'}</div>
  }

  const itemsTotal = (order.items ?? []).reduce(
    (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
    0
  )

  return (
    <>
      <header className="mb-6 flex items-center gap-3 pt-6">
        <button
          onClick={() => navigate('/orders')}
          className="rounded-lg border border-border bg-background px-3 py-1.5 text-sm hover:bg-surface"
        >
          رجوع
        </button>
        <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الطلب {order.order_number ?? `#${order.id}`}</h1>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="text-sm text-muted">الحالة</div>
          <div className="mt-2">
            <span
              className="rounded-full px-3 py-1 text-sm font-medium text-white"
              style={{ background: statusColors[order.status] || '#78716c' }}
            >
              {statusLabels[order.status] || order.status}
            </span>
          </div>
          {order.status === 'delivered' && (
            <button
              onClick={confirmReceipt}
              disabled={confirming}
              className="mt-4 w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
              {confirming ? 'جاري...' : 'تأكيد الاستلام'}
            </button>
          )}
        </div>

        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="text-sm text-muted">الفرع</div>
          <div className="mt-2 font-semibold text-foreground">{order.branch?.name ?? '-'}</div>
          <div className="text-sm text-muted">{order.branch?.city ?? ''}</div>
        </div>

        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="text-sm text-muted">التاريخ</div>
          <div className="mt-2 font-semibold text-foreground">
            {order.order_date ? new Date(order.order_date).toLocaleString('en-US') : '-'}
          </div>
          <div className="mt-2 text-sm text-muted">الإجمالي: {formatMoney(order.total_amount)} د.ل</div>
        </div>
      </div>

      <div className="mt-6 rounded-xl border border-border bg-surface p-5 shadow-sm">
        <h2 className="mb-4 font-semibold text-foreground">عناصر الطلب</h2>
        <table className="w-full text-sm">
          <thead className="border-b border-border text-muted">
            <tr>
              <th className="py-2 text-start">المنتج</th>
              <th className="py-2 text-start">الحجم</th>
              <th className="py-2 text-center">الكمية</th>
              <th className="py-2 text-end">سعر الوحدة</th>
              <th className="py-2 text-end">الإجمالي</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {(order.items ?? []).map((item) => {
              const variant = item.product_variant
              const product = variant?.product
              const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
              return (
                <tr key={item.id}>
                  <td className="py-3 text-foreground">{product?.name ?? 'منتج'}</td>
                  <td className="py-3 text-muted">{variant?.attribute_value ?? '-'}</td>
                  <td className="py-3 text-center">{item.quantity}</td>
                  <td className="py-3 text-end">{formatMoney(item.unit_price)} د.ل</td>
                  <td className="py-3 text-end font-medium">{formatMoney(lineTotal)} د.ل</td>
                </tr>
              )
            })}
          </tbody>
        </table>
        <div className="mt-4 flex justify-end border-t border-border pt-4">
          <div className="w-full max-w-xs space-y-2 text-sm">
            <div className="flex justify-between text-muted">
              <span>المجموع</span>
              <span>{formatMoney(itemsTotal)} د.ل</span>
            </div>
            <div className="flex justify-between text-muted">
              <span>التوصيل</span>
              <span>{formatMoney(order.delivery_fee)} د.ل</span>
            </div>
            <div className="flex justify-between text-lg font-bold text-foreground">
              <span>الإجمالي</span>
              <span>{formatMoney(order.total_amount)} د.ل</span>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
