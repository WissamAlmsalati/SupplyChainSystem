import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import Badge from '../components/ui/Badge'

const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
}

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
}

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function OrderDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const invoiceRef = useRef(null)

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const res = await client.get(`/orders/${id}`)
        setOrder(res.data?.data ?? res.data)
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل بيانات الطلب')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const handlePrint = () => {
    const originalTitle = document.title
    document.title = `فاتورة-${order.id}`
    window.print()
    setTimeout(() => {
      document.title = originalTitle
    }, 100)
  }

  const handleDownloadPdf = () => {
    const originalTitle = document.title
    document.title = `فاتورة-${order.id}`
    window.print()
    setTimeout(() => {
      document.title = originalTitle
    }, 100)
  }

  if (loading) return <div className="text-muted">جاري التحميل...</div>
  if (!order) return <div className="text-danger">{error || 'الطلب غير موجود.'}</div>

  const itemsTotal = (order.items ?? []).reduce(
    (sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0),
    0
  )
  const deliveryFee = Number(order.delivery_fee) || 0
  const total = Number(order.total_amount) || 0

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الطلب</h1>
          <p className="mt-1 text-muted">طلب #{order.id}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" onClick={() => navigate('/orders')}>العودة للطلبات</Button>
          <Button variant="primary" onClick={handlePrint}>طباعة</Button>
          <Button variant="primary" onClick={handleDownloadPdf}>تحميل PDF</Button>
        </div>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger print:hidden">
          {error}
        </div>
      )}

      <div className="grid gap-6 print:hidden lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>معلومات الطلب</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-muted">الحالة</span>
              <Badge variant={order.status === 'completed' || order.status === 'delivered' ? 'success' : order.status === 'cancelled' || order.status === 'failed' ? 'danger' : 'warning'}>
                {statusLabels[order.status] || order.status}
              </Badge>
            </div>
            {order.source === 'add order from dashboard' && (
              <div className="flex items-center justify-between">
                <span className="text-muted">المصدر</span>
                <Badge variant="primary">من الـ Dashboard</Badge>
              </div>
            )}
            <div className="flex items-center justify-between">
              <span className="text-muted">التاريخ</span>
              <span className="text-foreground">{order.order_date ? new Date(order.order_date).toLocaleString('en-US') : '-'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-muted">الإجمالي</span>
              <span className="font-semibold text-foreground">{formatMoney(total)} د.ل</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>العميل</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div><span className="text-muted">الاسم:</span> <span className="text-foreground">{order.user?.name ?? '-'}</span></div>
            <div><span className="text-muted">البريد:</span> <span className="text-foreground">{order.user?.email ?? '-'}</span></div>
            <div><span className="text-muted">الجوال:</span> <span className="text-foreground">{order.user?.mobile_number ?? '-'}</span></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>الفرع والتوصيل</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div><span className="text-muted">الفرع:</span> <span className="text-foreground">{order.branch?.name ?? '-'}</span></div>
            <div><span className="text-muted">المدينة:</span> <span className="text-foreground">{order.branch?.city ?? '-'}</span></div>
            <div><span className="text-muted">منطقة التوصيل:</span> <span className="text-foreground">{order.delivery_zone?.name ?? '-'}</span></div>
            <div><span className="text-muted">رسوم التوصيل:</span> <span className="text-foreground">{formatMoney(deliveryFee)} د.ل</span></div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6 print:hidden">
        <CardHeader>
          <CardTitle>عناصر الطلب</CardTitle>
        </CardHeader>
        <CardContent>
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
                {(order.items ?? []).map((item) => {
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
          <div className="mt-4 flex justify-end border-t border-border pt-4">
            <div className="w-full max-w-xs space-y-2 text-sm">
              <div className="flex justify-between text-muted">
                <span>المجموع</span>
                <span>{formatMoney(itemsTotal)} د.ل</span>
              </div>
              <div className="flex justify-between text-muted">
                <span>التوصيل</span>
                <span>{formatMoney(deliveryFee)} د.ل</span>
              </div>
              <div className="flex justify-between text-lg font-bold text-foreground">
                <span>الإجمالي</span>
                <span>{formatMoney(total)} د.ل</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Invoice */}
      <div className="mt-8 print:hidden">
        <h2 className="mb-4 text-xl font-bold text-foreground">فاتورة الطلب</h2>
      </div>

      <div ref={invoiceRef} className="invoice-page relative mx-auto max-w-4xl border border-border bg-white p-8 text-black shadow-sm sm:p-10">
        {/* Watermark */}
        <div className="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center gap-16 overflow-hidden opacity-60">
          <div className="rotate-[-30deg] text-8xl font-black text-gray-300 select-none sm:text-9xl">
            فاتورة ضريبية
          </div>
          <div className="rotate-[-30deg] text-8xl font-black text-gray-300 select-none sm:text-9xl">
            فاتورة ضريبية
          </div>
        </div>

        <div className="relative z-10">
          {/* Header */}
          <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="text-2xl font-bold tracking-tight">سلسلة إمداد المقاهي</div>
            <div className="mt-1 text-sm text-muted">نظام إدارة المخزون والطلبات</div>
            <div className="mt-3 text-xs text-muted">
              <div>ليبيا</div>
              <div>البريد: info@cafe-supply.ly</div>
              <div>الجوال: 091-0000000</div>
            </div>
          </div>
          <div className="sm:text-end">
            <div className="text-3xl font-bold">فاتورة</div>
            <div className="mt-2 text-sm text-muted">رقم الفاتورة: <span className="font-semibold text-foreground">INV-{order.id.toString().padStart(6, '0')}</span></div>
            <div className="text-sm text-muted">تاريخ الفاتورة: <span className="font-semibold text-foreground">{order.order_date ? new Date(order.order_date).toLocaleDateString('en-US') : '-'}</span></div>
            <div className="text-sm text-muted">تاريخ الاستحقاق: <span className="font-semibold text-foreground">{order.order_date ? new Date(order.order_date).toLocaleDateString('en-US') : '-'}</span></div>
          </div>
        </div>

        <hr className="border-border-strong" />

        {/* Bill to / Order details */}
        <div className="my-6 grid gap-6 sm:grid-cols-2">
          <div>
            <div className="mb-2 text-xs font-bold uppercase tracking-wide text-muted">فاتورة إلى</div>
            <div className="font-semibold">{order.user?.name ?? '-'}</div>
            <div className="text-sm text-muted">{order.user?.email ?? '-'}</div>
            <div className="text-sm text-muted">{order.user?.mobile_number ?? '-'}</div>
          </div>
          <div className="sm:text-end">
            <div className="mb-2 text-xs font-bold uppercase tracking-wide text-muted">تفاصيل الطلب</div>
            <div className="text-sm"><span className="text-muted">رقم الطلب:</span> #{order.id}</div>
            <div className="text-sm"><span className="text-muted">الفرع:</span> {order.branch?.name ?? '-'}</div>
            <div className="text-sm"><span className="text-muted">المدينة:</span> {order.branch?.city ?? '-'}</div>
            <div className="text-sm"><span className="text-muted">منطقة التوصيل:</span> {order.delivery_zone?.name ?? '-'}</div>
            <div className="text-sm"><span className="text-muted">الحالة:</span> {statusLabels[order.status] || order.status}</div>
          </div>
        </div>

        {/* Items table */}
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-y-2 border-foreground">
              <th className="py-2 text-start">#</th>
              <th className="py-2 text-start">المنتج</th>
              <th className="py-2 text-start">الوصف</th>
              <th className="py-2 text-center">الكمية</th>
              <th className="py-2 text-end">سعر الوحدة</th>
              <th className="py-2 text-end">الإجمالي</th>
            </tr>
          </thead>
          <tbody>
            {(order.items ?? []).map((item, idx) => {
              const lineTotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)
              const variant = item.product_variant
              const productName = variant?.product?.name ?? 'منتج'
              const size = variant?.attribute_value ?? '-'
              const description = variant?.attribute_name ? `${variant.attribute_name}: ${size}` : '-'
              return (
                <tr key={item.id} className="border-b border-border">
                  <td className="py-3">{idx + 1}</td>
                  <td className="py-3 font-medium">{productName}</td>
                  <td className="py-3 text-muted">{description}</td>
                  <td className="py-3 text-center">{item.quantity}</td>
                  <td className="py-3 text-end">{formatMoney(item.unit_price)} د.ل</td>
                  <td className="py-3 text-end font-medium">{formatMoney(lineTotal)} د.ل</td>
                </tr>
              )
            })}
            {deliveryFee > 0 && (
              <tr className="border-b border-border">
                <td className="py-3">{(order.items ?? []).length + 1}</td>
                <td className="py-3 font-medium">خدمة التوصيل</td>
                <td className="py-3 text-muted">{order.delivery_zone?.name ?? '-'}</td>
                <td className="py-3 text-center">1</td>
                <td className="py-3 text-end">{formatMoney(deliveryFee)} د.ل</td>
                <td className="py-3 text-end font-medium">{formatMoney(deliveryFee)} د.ل</td>
              </tr>
            )}
          </tbody>
        </table>

        {/* Totals */}
        <div className="mt-6 flex justify-end">
          <div className="w-full max-w-xs">
            <div className="flex justify-between py-2 text-sm">
              <span className="text-muted">المجموع الفرعي</span>
              <span className="font-medium">{formatMoney(itemsTotal)} د.ل</span>
            </div>
            <div className="flex justify-between py-2 text-sm">
              <span className="text-muted">رسوم التوصيل</span>
              <span className="font-medium">{formatMoney(deliveryFee)} د.ل</span>
            </div>
            <div className="flex justify-between border-t-2 border-foreground py-3 text-lg font-bold">
              <span>الإجمالي النهائي</span>
              <span>{formatMoney(total)} د.ل</span>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="mt-10 flex flex-col justify-between gap-6 border-t border-border pt-6 text-sm sm:flex-row">
          <div className="max-w-xs">
            <div className="mb-1 font-semibold">ملاحظات</div>
            <div className="text-muted">الأسعار بالدينار الليبي. الفاتورة صادرة إلكترونياً ولا تحتاج توقيعاً.</div>
          </div>
          <div>
            <div className="font-semibold">ختم / توقيع المستلم</div>
            <div className="mt-2 h-14 w-48 border-b border-border" />
          </div>
        </div>
        </div>
      </div>
    </>
  )
}
