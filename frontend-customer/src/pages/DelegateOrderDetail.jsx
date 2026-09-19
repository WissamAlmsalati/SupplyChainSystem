import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { Skeleton, SkeletonCard } from '../components/ui/Skeleton'

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

// What the driver may do next comes from the API (next_statuses), along with
// the cash to collect and the reasons to choose from when nobody is there.

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
  // null | 'deliver' | 'fail': handing over and giving up are both confirmed
  // first, because a mis-tap on "delivered" books cash the driver never took.
  const [panel, setPanel] = useState(null)
  const [reason, setReason] = useState('')
  const [failNote, setFailNote] = useState('')
  const [done, setDone] = useState('')
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

    const lat = Number(order.delivery_latitude)
    const lng = Number(order.delivery_longitude)
    const hasCoords = !isNaN(lat) && !isNaN(lng)
    const center = hasCoords ? [lat, lng] : [27.0, 17.0]

    const map = L.map(mapRef.current).setView(center, hasCoords ? 14 : 6)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    if (hasCoords) {
      L.marker([lat, lng], { icon: defaultIcon })
        .addTo(map)
        .bindPopup(`<b>${order.delivery_address_name ?? 'العنوان'}</b><br/>${order.delivery_city ?? ''}`)
        .openPopup()
    }

    mapInstanceRef.current = map
  }, [order])

  const updateStatus = async (status, extra = {}) => {
    setUpdating(true)
    setError('')
    setDone('')
    try {
      const res = await client.postOnce(`/delegate/orders/${id}/status`, { status, ...extra })
      if (Number(res.data?.cash_collected) > 0) {
        setDone(`تم تسجيل تحصيل ${Number(res.data.cash_collected).toFixed(2)} د.ل في عهدتك`)
      }
      setPanel(null)
      setReason('')
      setFailNote('')
      load()
    } catch (err) {
      const errors = err.response?.data?.errors
      setError(Object.values(errors ?? {})[0]?.[0] || err.response?.data?.message || 'فشل تحديث الحالة')
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
  const next = order.next_statuses ?? []
  const toCollect = Number(order.amount_to_collect) || 0
  const phones = order.delivery_phones?.length ? order.delivery_phones : order.user?.mobile_number ? [order.user.mobile_number] : []
  const hasPin = order.delivery_latitude != null && order.delivery_longitude != null

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
      {done && <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{done}</div>}
      {order.status === 'delivery_failed' && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          تعذّر التوصيل: {order.delivery_failure_label ?? ''}. المحاولة رقم {order.delivery_attempts}. البضاعة معك حتى يقرر المكتب.
        </div>
      )}
      {order.customer_note && (
        <div className="mb-4 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-foreground"><span className="font-bold">ملاحظة الزبون: </span>{order.customer_note}</div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-4">
          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">معلومات الطلب</h2>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between"><span className="text-muted">الحالة</span><span className="font-semibold">{statusLabels[order.status] || order.status}</span></div>
              <div className="flex justify-between"><span className="text-muted">التاريخ</span><span>{order.placed_at ? new Date(order.placed_at).toLocaleString('ar-LY') : '-'}</span></div>
              <div className="flex justify-between"><span className="text-muted">رسوم التوصيل</span><span>{formatMoney(deliveryFee)} د.ل</span></div>
              <div className="flex justify-between"><span className="text-muted">الإجمالي</span><span className="font-bold text-foreground">{formatMoney(order.total_amount)} د.ل</span></div>
              <div className="flex justify-between"><span className="text-muted">المطلوب تحصيله نقداً</span><span className={`font-extrabold ${toCollect > 0 ? 'text-warning' : 'text-success'}`}>{formatMoney(toCollect)} د.ل</span></div>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">العميل</h2>
            <div className="space-y-2 text-sm">
              <div><span className="text-muted">الاسم:</span> <span className="text-foreground">{order.user?.name ?? '-'}</span></div>
              <div className="flex flex-wrap gap-2 pt-1">
                {phones.map((ph) => <a key={ph} href={`tel:${ph}`} className="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-primary"><bdi>{ph}</bdi> اتصال</a>)}
                {hasPin && <a href={`https://www.google.com/maps/dir/?api=1&destination=${order.delivery_latitude},${order.delivery_longitude}`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-primary">فتح الملاحة</a>}
              </div>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">ماذا حدث؟</h2>
            {next.length === 0 ? (
              <p className="text-sm text-muted">لا يوجد إجراء مطلوب منك على هذا الطلب الآن.</p>
            ) : panel === 'deliver' ? (
              <div className="space-y-3">
                <p className="text-sm text-foreground">
                  {toCollect > 0
                    ? <>تأكيد التسليم يعني أنك استلمت من الزبون <span className="font-extrabold">{formatMoney(toCollect)} د.ل</span> نقداً، وتُسجَّل في عهدتك.</>
                    : 'الطلب مدفوع بالكامل، لا يوجد مبلغ للتحصيل.'}
                </p>
                <div className="flex gap-2">
                  <button disabled={updating} onClick={() => updateStatus('delivered')} className="flex-1 rounded-lg bg-primary px-3 py-2.5 text-sm font-bold text-primary-foreground disabled:opacity-60">{updating ? 'جارٍ الحفظ...' : 'نعم، سلّمت الطلب'}</button>
                  <button disabled={updating} onClick={() => setPanel(null)} className="rounded-lg border border-border px-3 py-2.5 text-sm">رجوع</button>
                </div>
              </div>
            ) : panel === 'fail' ? (
              <div className="space-y-3">
                <div className="grid gap-2">
                  {Object.entries(order.failure_reasons ?? {}).map(([key, label]) => (
                    <label key={key} className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm ${reason === key ? 'border-primary bg-primary/5' : 'border-border'}`}>
                      <input type="radio" name="reason" value={key} checked={reason === key} onChange={() => setReason(key)} />
                      {label}
                    </label>
                  ))}
                </div>
                <textarea rows={2} maxLength={255} value={failNote} onChange={(e) => setFailNote(e.target.value)} placeholder={reason === 'other' ? 'اكتب السبب (مطلوب)' : 'تفاصيل إضافية (اختياري)'} className="w-full rounded-lg border border-border px-3 py-2 text-sm" />
                <div className="flex gap-2">
                  <button disabled={updating || !reason || (reason === 'other' && !failNote.trim())} onClick={() => updateStatus('delivery_failed', { reason, note: failNote.trim() || null })} className="flex-1 rounded-lg bg-danger px-3 py-2.5 text-sm font-bold text-white disabled:opacity-50">{updating ? 'جارٍ الحفظ...' : 'تسجيل تعذّر التوصيل'}</button>
                  <button disabled={updating} onClick={() => setPanel(null)} className="rounded-lg border border-border px-3 py-2.5 text-sm">رجوع</button>
                </div>
              </div>
            ) : (
              <div className="grid gap-2">
                {next.includes('out_for_delivery') && (
                  <button disabled={updating} onClick={() => updateStatus('out_for_delivery')} className="rounded-lg bg-primary px-3 py-2.5 text-sm font-bold text-primary-foreground disabled:opacity-60">
                    {order.status === 'delivery_failed' ? 'خرجت لمحاولة توصيل أخرى' : 'استلمت الطلب وخرجت للتوصيل'}
                  </button>
                )}
                {next.includes('delivered') && <button disabled={updating} onClick={() => setPanel('deliver')} className="rounded-lg bg-primary px-3 py-2.5 text-sm font-bold text-primary-foreground disabled:opacity-60">تم التسليم</button>}
                {next.includes('delivery_failed') && <button disabled={updating} onClick={() => setPanel('fail')} className="rounded-lg border border-danger/40 px-3 py-2.5 text-sm font-medium text-danger disabled:opacity-60">تعذّر التوصيل</button>}
              </div>
            )}
          </div>
        </div>

        <div className="space-y-4">
          <div ref={mapRef} className="h-80 rounded-xl border border-border bg-surface shadow-sm" />

          <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h2 className="mb-3 font-bold text-foreground">العنوان</h2>
            <div className="space-y-2 text-sm">
              <div><span className="text-muted">الاسم:</span> <span className="text-foreground">{order.delivery_address_name ?? '-'}</span></div>
              <div><span className="text-muted">المدينة:</span> <span className="text-foreground">{order.delivery_city ?? '-'}</span></div>
              <div><span className="text-muted">الشارع:</span> <span className="text-foreground">{order.delivery_street ?? '-'}</span></div>
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
                const label = item.variant_name ? `${item.product_name} - ${item.variant_name}` : item.product_name
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
