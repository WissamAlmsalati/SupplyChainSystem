import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { Skeleton, SkeletonCard } from '../components/ui/Skeleton'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
}

const availableStatuses = ['pending', 'processing', 'completed', 'delivered', 'cancelled', 'failed']

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const defaultIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

export default function DelegateOrderDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [updating, setUpdating] = useState(false)
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/delegate/orders/${id}`)
      setOrder(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات الطلب')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  useEffect(() => {
    if (!order || mapInstanceRef.current) return

    const lat = Number(order.branch?.latitude)
    const lng = Number(order.branch?.longitude)
    const hasCoords = !isNaN(lat) && !isNaN(lng)
    const center = hasCoords ? [lat, lng] : [27.0, 17.0]

    const map = L.map(mapRef.current).setView(center, hasCoords ? 14 : 6)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    if (hasCoords) {
      L.marker([lat, lng], { icon: defaultIcon })
        .addTo(map)
        .bindPopup(`<b>${order.branch?.name ?? 'الفرع'}</b><br/>${order.branch?.city ?? ''}`)
        .openPopup()
    }

    mapInstanceRef.current = map
  }, [order])

  const updateStatus = async (status) => {
    setUpdating(true)
    try {
      await client.post(`/delegate/orders/${id}/status`, { status })
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الحالة')
    } finally {
      setUpdating(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6 pt-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <Skeleton className="h-8 w-40" />
            <Skeleton className="mt-2 h-4 w-24" />
          </div>
          <Skeleton className="h-10 w-28" />
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="space-y-4">
            <SkeletonCard />
            <SkeletonCard />
            <SkeletonCard />
          </div>
          <div className="space-y-4">
            <Skeleton className="h-80 rounded-xl" />
            <SkeletonCard />
          </div>
        </div>
        <SkeletonCard />
      </div>
    )
  }
  if (!order) return <div className="pt-6 text-danger">{error || 'الطلب غير موجود.'}</div>

  const items = order.items ?? []
  const itemsTotal = items.reduce(
    (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
    0
  )
  const deliveryFee = Number(order.delivery_fee) || 0

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الطلب</h1>
          <p className="mt-1 text-muted">طلب #{order.id}</p>
        </div>
        <button
          onClick={() => navigate('/orders')}
          className="rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-surface"
        >
          العودة للطلبات
        </button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-4">
          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">معلومات الطلب</h2>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between"><span className="text-muted">الحالة</span><span className="font-semibold">{statusLabels[order.status] || order.status}</span></div>
              <div className="flex justify-between"><span className="text-muted">التاريخ</span><span>{order.order_date ? new Date(order.order_date).toLocaleString('ar-LY') : '-'}</span></div>
              <div className="flex justify-between"><span className="text-muted">رسوم التوصيل</span><span>{formatMoney(deliveryFee)} د.ل</span></div>
              <div className="flex justify-between"><span className="text-muted">الإجمالي</span><span className="font-bold text-foreground">{formatMoney(order.total_amount)} د.ل</span></div>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">العميل</h2>
            <div className="space-y-2 text-sm">
              <div><span className="text-muted">الاسم:</span> <span className="text-foreground">{order.user?.name ?? '-'}</span></div>
              <div><span className="text-muted">البريد:</span> <span className="text-foreground">{order.user?.email ?? '-'}</span></div>
              <div><span className="text-muted">الجوال:</span> <span className="text-foreground">{order.user?.mobile_number ?? '-'}</span></div>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">تحديث الحالة</h2>
            <div className="flex flex-wrap gap-2">
              {availableStatuses.map((s) => (
                <button
                  key={s}
                  disabled={updating || order.status === s}
                  onClick={() => updateStatus(s)}
                  className={`rounded-lg px-3 py-2 text-xs font-medium transition ${
                    order.status === s
                      ? 'bg-primary text-primary-foreground'
                      : 'border border-border bg-background text-foreground hover:bg-surface'
                  } disabled:opacity-60`}
                >
                  {statusLabels[s]}
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div ref={mapRef} className="h-80 rounded-xl border border-border bg-surface shadow-sm" />

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">الفرع</h2>
            <div className="space-y-2 text-sm">
              <div><span className="text-muted">الاسم:</span> <span className="text-foreground">{order.branch?.name ?? '-'}</span></div>
              <div><span className="text-muted">المدينة:</span> <span className="text-foreground">{order.branch?.city ?? '-'}</span></div>
              <div><span className="text-muted">الشارع:</span> <span className="text-foreground">{order.branch?.street ?? '-'}</span></div>
              <div><span className="text-muted">خط العرض:</span> <span className="text-foreground">{order.branch?.latitude ?? '-'}</span></div>
              <div><span className="text-muted">خط الطول:</span> <span className="text-foreground">{order.branch?.longitude ?? '-'}</span></div>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6 rounded-xl border border-border bg-surface p-5 shadow-sm">
        <h2 className="mb-3 font-bold text-foreground">عناصر الطلب</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-border text-muted">
              <tr>
                <th className="py-2 text-start">المنتج</th>
                <th className="py-2 text-start">الكمية</th>
                <th className="py-2 text-start">سعر الوحدة</th>
                <th className="py-2 text-start">الإجمالي</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {items.map((item) => {
                const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
                const variant = item.product_variant
                const productName = variant?.product?.name ?? 'منتج'
                const label = variant?.attribute_value ? `${productName} - ${variant.attribute_value}` : productName
                return (
                  <tr key={item.id}>
                    <td className="py-3 text-foreground">{label}</td>
                    <td className="py-3 text-foreground">{item.quantity}</td>
                    <td className="py-3 text-foreground">{formatMoney(item.unit_price)} د.ل</td>
                    <td className="py-3 font-medium text-foreground">{formatMoney(lineTotal)} د.ل</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        <div className="mt-4 flex justify-between border-t border-border pt-4 text-sm">
          <span className="text-muted">المجموع</span>
          <span className="font-bold text-foreground">{formatMoney(itemsTotal + deliveryFee)} د.ل</span>
        </div>
      </div>
    </>
  )
}
