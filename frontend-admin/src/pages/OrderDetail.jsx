import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowRight, Printer, Check, X, Clock, CheckCircle2, Package, Truck, Home, PackageCheck,
  MapPin, Phone, User, Repeat, Smartphone, LayoutDashboard, CreditCard, History, Boxes, Trash2,
} from 'lucide-react'
import client from '../api/client'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import Modal from '../components/Modal'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { statusLabels, orderStatuses, StatusBadge } from '../lib/status'
import { PageSkeleton } from '../components/ui/Skeleton'
import { useModulePermission } from '../hooks/usePermission'
import { useApiList } from '../hooks/useApiResource'

function formatMoney(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-'
}

function formatDateTime(value) {
  return value ? new Date(value).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '-'
}

// Happy-path steps of an order (App\Enums\OrderStatus); cancelled/cancellation_requested sit outside it.
const STEPS = [
  { status: 'pending', label: 'قيد الانتظار', icon: Clock },
  { status: 'confirmed', label: 'مؤكد', icon: CheckCircle2 },
  { status: 'preparing', label: 'قيد التجهيز', icon: Package },
  { status: 'out_for_delivery', label: 'في الطريق', icon: Truck },
  { status: 'delivered', label: 'تم التوصيل', icon: Home },
  { status: 'received', label: 'استلمه الزبون', icon: PackageCheck },
]

// The action that moves the order one step forward.
const NEXT_ACTION = {
  pending: { to: 'confirmed', label: 'تأكيد الطلب' },
  confirmed: { to: 'preparing', label: 'بدء التجهيز' },
  preparing: { to: 'out_for_delivery', label: 'خرج للتوصيل' },
  out_for_delivery: { to: 'delivered', label: 'تم التوصيل' },
}

const PAYMENT_METHODS = { cash: 'نقداً', card: 'بطاقة', bank_transfer: 'تحويل بنكي' }
const PAYMENT_STATUSES = { pending: 'معلّق', paid: 'مدفوع', failed: 'فاشل', refunded: 'مسترجع' }
const MOVEMENT_TYPES = { sale: 'خصم للطلب', return: 'إرجاع للمخزون', purchase: 'شراء', adjustment: 'تعديل' }

const selectClass = 'w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'

function InfoRow({ label, children }) {
  return (
    <div className="flex items-start justify-between gap-4 py-1.5">
      <span className="shrink-0 text-muted">{label}</span>
      <span className="text-end text-foreground">{children ?? '-'}</span>
    </div>
  )
}

function StatusStepper({ status }) {
  const cancelled = status === 'cancelled'
  const currentIndex = STEPS.findIndex((s) => s.status === status)
  // A cancellation request is still a pending order until an admin decides.
  const activeIndex = status === 'cancellation_requested' ? 0 : currentIndex

  return (
    <ol className="flex items-start overflow-x-auto pb-1">
      {STEPS.map((step, i) => {
        const done = !cancelled && i < activeIndex
        const current = !cancelled && i === activeIndex
        const Icon = done ? Check : step.icon
        return (
          <li key={step.status} className="flex min-w-[88px] flex-1 flex-col items-center text-center">
            <div className="flex w-full items-center">
              <div className={`h-0.5 flex-1 ${i === 0 ? 'invisible' : done || current ? 'bg-primary' : 'bg-border'}`} />
              <div
                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 transition-colors ${
                  done ? 'border-primary bg-primary text-primary-foreground'
                    : current ? 'border-primary bg-primary/10 text-primary ring-4 ring-primary/10'
                    : 'border-border bg-surface text-muted'
                }`}
              >
                <Icon className="h-4 w-4" />
              </div>
              <div className={`h-0.5 flex-1 ${i === STEPS.length - 1 ? 'invisible' : done ? 'bg-primary' : 'bg-border'}`} />
            </div>
            <span className={`mt-2 text-xs ${current ? 'font-bold text-primary' : done ? 'text-foreground' : 'text-muted'}`}>
              {step.label}
            </span>
          </li>
        )
      })}
    </ol>
  )
}

export default function OrderDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [order, setOrder] = useState(null)
  const [movements, setMovements] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const invoiceRef = useRef(null)
  const { canEdit, canDelete } = useModulePermission('ORDERS')
  const { canCreate: canCreatePayment } = useModulePermission('PAYMENTS')
  const delegates = useApiList('/delegates?per_page=1000')
  const [statusModal, setStatusModal] = useState(false)
  const [newStatus, setNewStatus] = useState('')
  const [delegateModal, setDelegateModal] = useState(false)
  const [selectedDelegate, setSelectedDelegate] = useState('')
  const [paymentModal, setPaymentModal] = useState(false)
  const [payment, setPayment] = useState({ amount: '', method: 'cash', status: 'paid' })
  const [saving, setSaving] = useState(false)

  const load = async () => {
    setError('')
    try {
      const res = await client.get(`/orders/${id}`)
      setOrder(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات الطلب')
    }
    // Stock ledger rows for this order (where the items were taken from / returned to).
    try {
      const res = await client.get('/stock-movements', { params: { reference_type: 'App\\Models\\Order', reference_id: id, per_page: 100 } })
      setMovements(res.data?.data ?? [])
    } catch {
      setMovements([])
    }
  }

  useEffect(() => {
    async function initial() {
      setLoading(true)
      await load()
      setLoading(false)
    }
    initial()
  }, [id])

  const changeStatus = async (status, question) => {
    if (question && !window.confirm(question)) return
    setSaving(true)
    setError('')
    try {
      await client.put(`/orders/${id}`, { status })
      setStatusModal(false)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الحالة')
    } finally {
      setSaving(false)
    }
  }

  const saveStatus = (e) => {
    e.preventDefault()
    const question = newStatus === 'cancelled' ? 'إلغاء الطلب سيُرجع الكميات إلى المخزون. متأكد؟' : null
    changeStatus(newStatus, question)
  }

  const saveDelegate = async (e) => {
    e.preventDefault()
    if (!selectedDelegate) return
    setSaving(true)
    try {
      await client.post(`/orders/${id}/assign-delegate`, { delegate_id: Number(selectedDelegate) })
      setDelegateModal(false)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تعيين المندوب')
    } finally {
      setSaving(false)
    }
  }

  const openPayment = () => {
    setPayment({ amount: String(Math.max(0, balance).toFixed(2)), method: 'cash', status: 'paid' })
    setPaymentModal(true)
  }

  const savePayment = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await client.post('/payments', {
        order_id: order.id,
        amount: Number(payment.amount),
        method: payment.method,
        status: payment.status,
        paid_at: payment.status === 'paid' ? new Date().toISOString() : null,
      })
      setPaymentModal(false)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تسجيل الدفعة')
    } finally {
      setSaving(false)
    }
  }

  const deleteOrder = async () => {
    if (!window.confirm('حذف الطلب نهائياً؟')) return
    try {
      await client.delete(`/orders/${id}`)
      navigate('/orders')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف الطلب')
    }
  }

  const handlePrint = () => {
    const originalTitle = document.title
    document.title = `فاتورة-${order.order_number ?? order.id}`
    window.print()
    setTimeout(() => {
      document.title = originalTitle
    }, 100)
  }

  if (loading) return <PageSkeleton />
  if (!order) return <div className="text-danger">{error || 'الطلب غير موجود.'}</div>

  const items = order.items ?? []
  const itemsTotal = items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0)
  const deliveryFee = Number(order.delivery_fee) || 0
  const total = Number(order.total_amount) || 0
  const paid = (order.payments ?? []).filter((p) => p.status === 'paid').reduce((s, p) => s + Number(p.amount), 0)
  const refunded = (order.payments ?? []).filter((p) => p.status === 'refunded').reduce((s, p) => s + Number(p.amount), 0)
  // A cancelled order owes nothing.
  const balance = order.status === 'cancelled' ? 0 : total - paid + refunded
  const next = NEXT_ACTION[order.status]
  const isCancelled = order.status === 'cancelled'
  const isClosed = isCancelled || order.status === 'delivered' || order.status === 'received'
  const hasCoords = order.delivery_latitude != null && order.delivery_longitude != null
  const mapUrl = hasCoords ? `https://www.google.com/maps?q=${order.delivery_latitude},${order.delivery_longitude}` : null
  const phones = order.delivery_phones?.length ? order.delivery_phones : order.user?.mobile_number ? [order.user.mobile_number] : []
  const activeDelegates = delegates.filter((d) => d.is_active)
  const logs = [...(order.status_logs ?? [])].reverse()
  const invoiceNumber = order.order_number ? order.order_number.replace(/^ORD-/, 'INV-') : `INV-${String(order.id).padStart(6, '0')}`
  const paymentMethodsLabel = [...new Set((order.payments ?? []).filter((p) => p.status === 'paid').map((p) => PAYMENT_METHODS[p.method] ?? p.method))].join('، ') || '-'
  const invoiceStamp = isCancelled
    ? { label: 'ملغاة', className: 'border-red-600 text-red-600' }
    : balance <= 0.004
      ? { label: 'مدفوعة', className: 'border-green-700 text-green-700' }
      : paid - refunded > 0
        ? { label: 'مدفوعة جزئياً', className: 'border-amber-600 text-amber-600' }
        : { label: 'غير مدفوعة', className: 'border-stone-400 text-stone-400' }

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 print:hidden lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-start gap-3">
          <button
            onClick={() => navigate('/orders')}
            className="mt-1 rounded-md border border-border p-2 text-muted hover:bg-background hover:text-foreground"
            aria-label="العودة للطلبات"
          >
            <ArrowRight className="h-4 w-4" />
          </button>
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-extrabold text-foreground" dir="ltr">{order.order_number ?? `#${order.id}`}</h1>
              <StatusBadge status={order.status} />
              <Badge variant="info">
                <span className="inline-flex items-center gap-1">
                  {order.source === 'dashboard' ? <LayoutDashboard className="h-3 w-3" /> : <Smartphone className="h-3 w-3" />}
                  {order.source === 'dashboard' ? 'من لوحة التحكم' : 'من التطبيق'}
                </span>
              </Badge>
              {order.cart?.type === 'recurring' && (
                <Badge variant="default">
                  <span className="inline-flex items-center gap-1"><Repeat className="h-3 w-3" /> طلبية متكررة: {order.cart.name}</span>
                </Badge>
              )}
            </div>
            <p className="mt-1 text-sm text-muted">
              {formatDateTime(order.placed_at)} · {order.user?.name ?? '-'} · {items.length} صنف
            </p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {canEdit && next && (
            <Button variant="primary" onClick={() => changeStatus(next.to)} disabled={saving}>
              <Check className="h-4 w-4" /> {next.label}
            </Button>
          )}
          {canEdit && !isClosed && order.status !== 'cancellation_requested' && (
            <Button
              variant="secondary"
              className="text-danger"
              disabled={saving}
              onClick={() => changeStatus('cancelled', 'إلغاء الطلب سيُرجع الكميات إلى المخزون. متأكد؟')}
            >
              <X className="h-4 w-4" /> إلغاء الطلب
            </Button>
          )}
          <Button variant="secondary" onClick={handlePrint}>
            <Printer className="h-4 w-4" /> طباعة / PDF
          </Button>
          {canDelete && isCancelled && (
            <Button variant="danger" onClick={deleteOrder}>
              <Trash2 className="h-4 w-4" /> حذف
            </Button>
          )}
        </div>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger print:hidden">
          {error}
        </div>
      )}

      {order.status === 'cancellation_requested' && (
        <div className="mb-4 flex flex-col gap-3 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 print:hidden sm:flex-row sm:items-center sm:justify-between">
          <div className="text-sm text-warning">
            <span className="font-bold">طلب إلغاء من الزبون.</span> الموافقة تلغي الطلب وتُرجع الكميات إلى المخزون.
          </div>
          {canEdit && (
            <div className="flex gap-2">
              <Button variant="danger" size="sm" disabled={saving} onClick={() => changeStatus('cancelled', 'الموافقة على إلغاء الطلب؟')}>
                موافقة على الإلغاء
              </Button>
              <Button variant="secondary" size="sm" disabled={saving} onClick={() => changeStatus('pending')}>
                رفض
              </Button>
            </div>
          )}
        </div>
      )}

      <Card className="mb-6 print:hidden">
        <CardContent className="pt-6">
          {isCancelled ? (
            <div className="flex items-center gap-3 text-danger">
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-danger-soft"><X className="h-4 w-4" /></div>
              <div>
                <div className="font-bold">الطلب ملغي</div>
                <div className="text-sm text-muted">تم إرجاع الكميات إلى المخزون تلقائياً.</div>
              </div>
            </div>
          ) : (
            <StatusStepper status={order.status} />
          )}
          {canEdit && (
            <div className="mt-4 flex justify-end">
              <button onClick={() => { setNewStatus(order.status); setStatusModal(true) }} className="text-xs text-muted underline hover:text-foreground">
                تعيين حالة يدوياً
              </button>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-6 print:hidden lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>عناصر الطلب</CardTitle>
              <span className="text-xs text-muted">الأسعار والأسماء محفوظة كما كانت وقت الطلب</span>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border text-muted">
                    <tr>
                      <th className="py-2 text-start font-medium">المنتج</th>
                      <th className="py-2 text-center font-medium">الكمية</th>
                      <th className="py-2 text-end font-medium">سعر الوحدة</th>
                      <th className="py-2 text-end font-medium">الإجمالي</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {items.map((item) => {
                      const variant = item.product_variant
                      const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
                      const priceChanged = variant && Number(variant.price) !== Number(item.unit_price)
                      return (
                        <tr key={item.id}>
                          <td className="py-3">
                            <Link to={`/product-variants/${item.product_variant_id}`} className="font-medium text-foreground hover:text-primary">
                              {item.product_name}
                            </Link>
                            <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted">
                              {item.variant_name && <span>الحجم: {item.variant_name}</span>}
                              {variant?.sku && <code dir="ltr">{variant.sku}</code>}
                              {variant?.deleted_at && <Badge variant="danger">محذوف من الكتالوج</Badge>}
                            </div>
                          </td>
                          <td className="py-3 text-center text-foreground">{item.quantity}</td>
                          <td className="py-3 text-end text-foreground">
                            {formatMoney(item.unit_price)} د.ل
                            {priceChanged && <div className="text-xs text-muted">الحالي: {formatMoney(variant.price)}</div>}
                          </td>
                          <td className="py-3 text-end font-semibold text-foreground">{formatMoney(lineTotal)} د.ل</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
              <div className="mt-4 flex justify-end border-t border-border pt-4">
                <div className="w-full max-w-xs space-y-2 text-sm">
                  <div className="flex justify-between text-muted"><span>المجموع</span><span>{formatMoney(order.subtotal ?? itemsTotal)} د.ل</span></div>
                  <div className="flex justify-between text-muted"><span>التوصيل</span><span>{formatMoney(deliveryFee)} د.ل</span></div>
                  <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground"><span>الإجمالي</span><span>{formatMoney(total)} د.ل</span></div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="flex items-center gap-2"><CreditCard className="h-4 w-4 text-muted" /> المدفوعات</CardTitle>
              {canCreatePayment && !isCancelled && (
                <Button variant="secondary" size="sm" onClick={openPayment}>تسجيل دفعة</Button>
              )}
            </CardHeader>
            <CardContent>
              <div className="mb-4 grid grid-cols-3 gap-3 text-center">
                <div className="rounded-lg bg-background p-3">
                  <div className="text-xs text-muted">الإجمالي</div>
                  <div className="mt-1 font-bold text-foreground">{formatMoney(total)}</div>
                </div>
                <div className="rounded-lg bg-success-soft p-3">
                  <div className="text-xs text-muted">المدفوع</div>
                  <div className="mt-1 font-bold text-success">{formatMoney(paid - refunded)}</div>
                </div>
                <div className={`rounded-lg p-3 ${balance > 0.004 ? 'bg-warning-soft' : 'bg-background'}`}>
                  <div className="text-xs text-muted">المتبقي</div>
                  <div className={`mt-1 font-bold ${balance > 0.004 ? 'text-warning' : 'text-foreground'}`}>{formatMoney(Math.max(0, balance))}</div>
                </div>
              </div>
              {(order.payments ?? []).length === 0 ? (
                <div className="text-sm text-muted">لا توجد مدفوعات مسجلة.</div>
              ) : (
                <ul className="divide-y divide-border text-sm">
                  {order.payments.map((p) => (
                    <li key={p.id} className="flex items-center justify-between py-2">
                      <span className="text-foreground">{PAYMENT_METHODS[p.method] ?? p.method}</span>
                      <span className="text-muted">{formatDateTime(p.paid_at ?? p.created_at)}</span>
                      <Badge variant={p.status === 'paid' ? 'success' : p.status === 'pending' ? 'warning' : 'danger'}>{PAYMENT_STATUSES[p.status] ?? p.status}</Badge>
                      <span className="font-semibold text-foreground">{formatMoney(p.amount)} د.ل</span>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>

          {movements.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2"><Boxes className="h-4 w-4 text-muted" /> حركة المخزون</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="border-b border-border text-muted">
                      <tr>
                        <th className="py-2 text-start font-medium">الحركة</th>
                        <th className="py-2 text-start font-medium">المستودع</th>
                        <th className="py-2 text-start font-medium">الصنف</th>
                        <th className="py-2 text-center font-medium">الكمية</th>
                        <th className="py-2 text-end font-medium">الوقت</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {movements.map((m) => (
                        <tr key={m.id}>
                          <td className="py-2"><Badge variant={m.quantity_change < 0 ? 'warning' : 'success'}>{MOVEMENT_TYPES[m.type] ?? m.type}</Badge></td>
                          <td className="py-2 text-foreground">{m.warehouse?.name ?? '-'}</td>
                          <td className="py-2 text-foreground">{m.product_variant?.product?.name} {m.product_variant?.name ? `— ${m.product_variant.name}` : ''}</td>
                          <td className={`py-2 text-center font-semibold ${m.quantity_change < 0 ? 'text-danger' : 'text-success'}`} dir="ltr">
                            {m.quantity_change > 0 ? `+${m.quantity_change}` : m.quantity_change}
                          </td>
                          <td className="py-2 text-end text-muted">{formatDateTime(m.created_at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          )}
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><User className="h-4 w-4 text-muted" /> الزبون</CardTitle></CardHeader>
            <CardContent className="text-sm">
              <InfoRow label="الاسم">
                {order.user ? <Link to={`/users/${order.user.id}`} className="font-medium hover:text-primary">{order.user.name}</Link> : '-'}
              </InfoRow>
              <InfoRow label="الجوال">
                {order.user?.mobile_number ? <a href={`tel:${order.user.mobile_number}`} dir="ltr" className="hover:text-primary">{order.user.mobile_number}</a> : '-'}
              </InfoRow>
              <InfoRow label="البريد">{order.user?.email}</InfoRow>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><MapPin className="h-4 w-4 text-muted" /> التوصيل</CardTitle></CardHeader>
            <CardContent className="text-sm">
              <InfoRow label="العنوان">
                {order.address_id ? <Link to={`/addresses/${order.address_id}`} className="font-medium hover:text-primary">{order.delivery_address_name}</Link> : order.delivery_address_name}
              </InfoRow>
              <InfoRow label="المدينة">{order.delivery_city}</InfoRow>
              <InfoRow label="الشارع">{order.delivery_street}</InfoRow>
              <InfoRow label="المنطقة">{order.delivery_zone?.name}</InfoRow>
              <InfoRow label="رسوم التوصيل">{formatMoney(deliveryFee)} د.ل</InfoRow>
              {phones.length > 0 && (
                <InfoRow label="هاتف التواصل">
                  <span className="flex flex-col items-end">
                    {phones.map((ph) => <a key={ph} href={`tel:${ph}`} dir="ltr" className="hover:text-primary">{ph}</a>)}
                  </span>
                </InfoRow>
              )}
              {mapUrl && (
                <a href={mapUrl} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                  <MapPin className="h-4 w-4" /> فتح الموقع على الخريطة
                </a>
              )}
              {order.address?.deleted_at && (
                <div className="mt-3 text-xs text-muted">العنوان حُذف من حساب الزبون؛ البيانات أعلاه نسخة وقت الطلب.</div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="flex items-center gap-2"><Truck className="h-4 w-4 text-muted" /> المندوب</CardTitle>
              {canEdit && !isClosed && (
                <Button variant="secondary" size="sm" onClick={() => { setSelectedDelegate(order.delegate?.id ? String(order.delegate.id) : ''); setDelegateModal(true) }}>
                  {order.delegate ? 'تغيير' : 'تعيين'}
                </Button>
              )}
            </CardHeader>
            <CardContent className="text-sm">
              {order.delegate ? (
                <>
                  <InfoRow label="الاسم">
                    <Link to={`/delegates/${order.delegate.id}`} className="font-medium hover:text-primary">{order.delegate.name}</Link>
                  </InfoRow>
                  <InfoRow label="الجوال">
                    {order.delegate.mobile_number
                      ? <a href={`tel:${order.delegate.mobile_number}`} dir="ltr" className="inline-flex items-center gap-1 hover:text-primary"><Phone className="h-3 w-3" />{order.delegate.mobile_number}</a>
                      : '-'}
                  </InfoRow>
                </>
              ) : (
                <div className="text-muted">لم يُعيَّن مندوب بعد.</div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><History className="h-4 w-4 text-muted" /> سجل الحالات</CardTitle></CardHeader>
            <CardContent>
              {logs.length === 0 ? (
                <div className="text-sm text-muted">لا يوجد سجل.</div>
              ) : (
                <ol className="relative space-y-4 border-s border-border ps-5">
                  {logs.map((log, i) => (
                    <li key={log.id} className="relative">
                      <span className={`absolute -start-[27px] top-1 h-3 w-3 rounded-full border-2 border-surface ${i === 0 ? 'bg-primary' : 'bg-border-strong'}`} />
                      <div className="flex flex-wrap items-center gap-1.5 text-sm">
                        {log.from_status && <><span className="text-muted">{statusLabels[log.from_status] ?? log.from_status}</span><span className="text-muted">←</span></>}
                        <span className="font-semibold text-foreground">{statusLabels[log.to_status] ?? log.to_status}</span>
                      </div>
                      <div className="mt-0.5 text-xs text-muted">
                        {formatDateTime(log.created_at)} · {log.changed_by?.name ?? 'النظام'}
                      </div>
                      {log.note && <div className="mt-1 text-xs text-foreground">{log.note}</div>}
                    </li>
                  ))}
                </ol>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Invoice */}
      <div className="mt-8 flex items-center justify-between print:hidden">
        <h2 className="text-xl font-bold text-foreground">معاينة الفاتورة</h2>
        <Button variant="secondary" size="sm" onClick={handlePrint}><Printer className="h-4 w-4" /> طباعة الفاتورة</Button>
      </div>

      <div ref={invoiceRef} className="invoice-page relative mx-auto mt-4 max-w-4xl overflow-hidden rounded-xl border border-border bg-white text-[13px] text-stone-800 shadow-sm">
        <div className="h-2 bg-primary" />

        <div className="p-8 sm:p-10">
          {/* Header */}
          <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-start gap-4">
              <img src="/favicon.svg" alt="الساحل" className="h-16 w-16 rounded-lg object-contain" />
              <div>
                <div className="text-xl font-extrabold text-stone-900">الساحل لمستلزمات المقاهي</div>
                <div className="mt-0.5 text-stone-500">توريد مستلزمات المقاهي بالجملة</div>
                <div className="mt-3 space-y-0.5 text-xs text-stone-500">
                  <div>ليبيا — طرابلس</div>
                  <div><bdi>info@cafe-supply.ly</bdi> · <bdi>091-0000000</bdi></div>
                </div>
              </div>
            </div>
            <div className="sm:text-end">
              <div className="text-3xl font-black text-primary">فاتورة</div>
              <table className="mt-3 text-xs sm:ms-auto">
                <tbody>
                  <tr><td className="pe-4 py-0.5 text-stone-500">رقم الفاتورة</td><td className="py-0.5 font-bold"><bdi>{invoiceNumber}</bdi></td></tr>
                  <tr><td className="pe-4 py-0.5 text-stone-500">رقم الطلب</td><td className="py-0.5 font-semibold"><bdi>{order.order_number ?? `#${order.id}`}</bdi></td></tr>
                  <tr><td className="pe-4 py-0.5 text-stone-500">تاريخ الطلب</td><td className="py-0.5 font-semibold">{formatDate(order.placed_at)}</td></tr>
                  <tr><td className="pe-4 py-0.5 text-stone-500">تاريخ الإصدار</td><td className="py-0.5 font-semibold">{formatDate(new Date())}</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* Parties */}
          <div className="no-break mt-8 grid gap-4 sm:grid-cols-3">
            <div className="rounded-lg bg-stone-50 p-4">
              <div className="mb-2 text-[11px] font-bold text-primary">فاتورة إلى</div>
              <div className="font-bold text-stone-900">{order.user?.customer_profile?.business_name || order.user?.name || '-'}</div>
              {order.user?.customer_profile?.business_name && order.user?.name !== order.user.customer_profile.business_name && (
                <div className="text-stone-600">{order.user.name}</div>
              )}
              {order.user?.mobile_number && <div className="mt-1 text-stone-600"><bdi>{order.user.mobile_number}</bdi></div>}
              {order.user?.email && <div className="text-stone-600"><bdi>{order.user.email}</bdi></div>}
            </div>
            <div className="rounded-lg bg-stone-50 p-4">
              <div className="mb-2 text-[11px] font-bold text-primary">التوصيل إلى</div>
              <div className="font-bold text-stone-900">{order.delivery_address_name ?? '-'}</div>
              <div className="text-stone-600">{[order.delivery_street, order.delivery_city].filter(Boolean).join('، ') || '-'}</div>
              {order.delivery_zone?.name && <div className="text-stone-600">{order.delivery_zone.name}</div>}
              {phones.length > 0 && <div className="mt-1 text-stone-600">{phones.map((ph, i) => <span key={ph}>{i > 0 && ' · '}<bdi>{ph}</bdi></span>)}</div>}
            </div>
            <div className="rounded-lg bg-stone-50 p-4">
              <div className="mb-2 text-[11px] font-bold text-primary">تفاصيل الطلب</div>
              <div className="flex justify-between gap-2"><span className="text-stone-500">الحالة</span><span className="font-semibold">{statusLabels[order.status] || order.status}</span></div>
              <div className="flex justify-between gap-2"><span className="text-stone-500">المصدر</span><span className="font-semibold">{order.source === 'dashboard' ? 'لوحة التحكم' : 'التطبيق'}</span></div>
              <div className="flex justify-between gap-2"><span className="text-stone-500">المندوب</span><span className="font-semibold">{order.delegate?.name ?? '-'}</span></div>
              <div className="flex justify-between gap-2"><span className="text-stone-500">الدفع</span><span className="font-semibold">{paymentMethodsLabel}</span></div>
            </div>
          </div>

          {/* Items */}
          <table className="mt-8 w-full border-collapse">
            <thead>
              <tr className="bg-primary text-primary-foreground">
                <th className="rounded-s-md px-3 py-2.5 text-start font-semibold">#</th>
                <th className="px-3 py-2.5 text-start font-semibold">الصنف</th>
                <th className="px-3 py-2.5 text-center font-semibold">الكمية</th>
                <th className="px-3 py-2.5 text-end font-semibold">سعر الوحدة</th>
                <th className="rounded-e-md px-3 py-2.5 text-end font-semibold">الإجمالي</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item, idx) => {
                const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
                return (
                  <tr key={item.id} className="border-b border-stone-200 even:bg-stone-50/70">
                    <td className="px-3 py-3 text-stone-500">{idx + 1}</td>
                    <td className="px-3 py-3">
                      <div className="font-semibold text-stone-900">{item.product_name}</div>
                      <div className="text-xs text-stone-500">
                        {item.variant_name && <span>الحجم: <bdi>{item.variant_name}</bdi></span>}
                        {item.variant_name && item.product_variant?.sku && <span className="mx-1.5 text-stone-300">|</span>}
                        {item.product_variant?.sku && <bdi className="font-mono">{item.product_variant.sku}</bdi>}
                      </div>
                    </td>
                    <td className="px-3 py-3 text-center font-semibold">{item.quantity}</td>
                    <td className="px-3 py-3 text-end">{formatMoney(item.unit_price)}</td>
                    <td className="px-3 py-3 text-end font-semibold text-stone-900">{formatMoney(lineTotal)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>

          {/* Totals */}
          <div className="no-break mt-6 flex flex-col-reverse gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="max-w-sm text-xs leading-relaxed text-stone-500">
              <div className="mb-1 font-bold text-stone-700">ملاحظات</div>
              <p>جميع المبالغ بالدينار الليبي (د.ل). يرجى التحقق من الأصناف والكميات عند الاستلام.</p>
              <p className="mt-2 font-semibold text-primary">شكراً لتعاملكم مع الساحل.</p>
              {/* Payment status stamp */}
              <div className={`mt-8 inline-block rotate-[-8deg] rounded-md border-[3px] px-5 py-1.5 text-2xl font-black tracking-wide opacity-80 ${invoiceStamp.className}`}>
                {invoiceStamp.label}
              </div>
            </div>
            <div className="w-full overflow-hidden rounded-lg border border-stone-200 sm:w-72">
              <div className="flex justify-between px-4 py-2"><span className="text-stone-500">المجموع الفرعي</span><span className="font-semibold">{formatMoney(order.subtotal ?? itemsTotal)}</span></div>
              <div className="flex justify-between px-4 py-2"><span className="text-stone-500">رسوم التوصيل</span><span className="font-semibold">{formatMoney(deliveryFee)}</span></div>
              <div className="flex justify-between bg-primary px-4 py-3 text-base font-extrabold text-primary-foreground"><span>الإجمالي</span><span>{formatMoney(total)} د.ل</span></div>
              {!isCancelled && (
                <>
                  <div className="flex justify-between px-4 py-2"><span className="text-stone-500">المدفوع</span><span className="font-semibold text-green-700">{formatMoney(paid - refunded)}</span></div>
                  <div className="flex justify-between border-t border-stone-200 px-4 py-2"><span className="font-bold text-stone-700">المتبقي</span><span className="font-extrabold text-stone-900">{formatMoney(Math.max(0, balance))} د.ل</span></div>
                </>
              )}
            </div>
          </div>

          {/* Signatures */}
          <div className="no-break mt-12 grid grid-cols-2 gap-10">
            <div>
              <div className="h-12 border-b border-dashed border-stone-400" />
              <div className="mt-2 text-xs text-stone-500">توقيع المندوب{order.delegate?.name ? ` — ${order.delegate.name}` : ''}</div>
            </div>
            <div>
              <div className="h-12 border-b border-dashed border-stone-400" />
              <div className="mt-2 text-xs text-stone-500">توقيع وختم المستلم</div>
            </div>
          </div>
        </div>

        <div className="flex items-center justify-between border-t border-stone-200 bg-stone-50 px-8 py-3 text-[11px] text-stone-400 sm:px-10">
          <span>الساحل لمستلزمات المقاهي</span>
          <bdi>{invoiceNumber}</bdi>
        </div>
      </div>

      <Modal title="تعيين حالة يدوياً" open={statusModal} onClose={() => setStatusModal(false)}>
        <form onSubmit={saveStatus} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الحالة</label>
            <select className={selectClass} value={newStatus} onChange={(e) => setNewStatus(e.target.value)} required>
              {orderStatuses.map((key) => (
                <option key={key} value={key}>{statusLabels[key] || key}</option>
              ))}
            </select>
          </div>
          <div className="mt-6 flex items-center justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setStatusModal(false)}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving || newStatus === order.status}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>

      <Modal title={order.delegate ? 'تغيير المندوب' : 'تعيين مندوب'} open={delegateModal} onClose={() => setDelegateModal(false)}>
        <form onSubmit={saveDelegate} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المندوب</label>
            <select className={selectClass} value={selectedDelegate} onChange={(e) => setSelectedDelegate(e.target.value)} required>
              <option value="">اختر مندوب</option>
              {activeDelegates.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name} {d.delegate_profile?.is_available ? '— متاح' : '— غير متاح'}
                </option>
              ))}
            </select>
          </div>
          <div className="mt-6 flex items-center justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setDelegateModal(false)}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>

      <Modal title="تسجيل دفعة" open={paymentModal} onClose={() => setPaymentModal(false)}>
        <form onSubmit={savePayment} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المبلغ (د.ل)</label>
            <input type="number" min="0.01" step="0.01" className={selectClass} value={payment.amount} onChange={(e) => setPayment({ ...payment, amount: e.target.value })} required />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">طريقة الدفع</label>
              <select className={selectClass} value={payment.method} onChange={(e) => setPayment({ ...payment, method: e.target.value })}>
                {Object.entries(PAYMENT_METHODS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">الحالة</label>
              <select className={selectClass} value={payment.status} onChange={(e) => setPayment({ ...payment, status: e.target.value })}>
                {Object.entries(PAYMENT_STATUSES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </div>
          </div>
          <div className="mt-6 flex items-center justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setPaymentModal(false)}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
