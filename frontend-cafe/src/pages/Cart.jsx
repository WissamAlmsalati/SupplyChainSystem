import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useCart } from '../context/CartContext'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Cart() {
  const navigate = useNavigate()
  const { cart, updateItem, removeItem, clearCart, checkout } = useCart()
  const [addresses, setAddresses] = useState([])
  const [addressId, setAddressId] = useState('')
  const [updating, setUpdating] = useState(null)
  const [checkingOut, setCheckingOut] = useState(false)
  const [saving, setSaving] = useState(false)
  const [recurringName, setRecurringName] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  useEffect(() => {
    client.get('/cafe/addresses')
      .then((res) => {
        const list = res.data?.data?.addresses ?? []
        setAddresses(list)
        const preferred = list.find((a) => a.is_default) ?? list[0]
        if (preferred) setAddressId(String(preferred.id))
      })
      .catch(() => setAddresses([]))
  }, [])

  const items = cart?.items ?? []
  const address = addresses.find((a) => String(a.id) === addressId)
  const unitPrice = (item) => Number(item.product_variant?.price) || 0
  const subtotal = items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * unitPrice(item), 0)
  const deliveryFee = Number(address?.delivery_zone?.delivery_price) || 0
  const total = subtotal + deliveryFee

  const handleUpdate = async (item, quantity) => {
    if (quantity < 1) return
    setUpdating(item.id)
    setError('')
    try {
      await updateItem(item.id, quantity)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الكمية')
    } finally {
      setUpdating(null)
    }
  }

  const handleRemove = async (item) => {
    setUpdating(item.id)
    setError('')
    try {
      await removeItem(item.id)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف العنصر')
    } finally {
      setUpdating(null)
    }
  }

  const handleCheckout = async () => {
    if (items.length === 0) return
    if (!addressId) {
      setError('اختر عنوان التوصيل أولاً')
      return
    }
    setCheckingOut(true)
    setError('')
    setSuccess('')
    try {
      const res = await checkout(Number(addressId))
      const orderNumber = res.data?.order_number ?? res.data?.id
      setSuccess(`${res.message || 'تم إنشاء الطلب بنجاح'} — رقم الطلب: ${orderNumber}`)
      setTimeout(() => navigate('/orders'), 2000)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إتمام الطلب')
    } finally {
      setCheckingOut(false)
    }
  }

  // Saves the current items as a named recurring cart the customer can re-order later.
  const handleSaveRecurring = async () => {
    if (!recurringName.trim() || items.length === 0) return
    setSaving(true)
    setError('')
    try {
      await client.post('/cafe/recurring-carts', {
        name: recurringName.trim(),
        items: items.map((i) => ({ product_variant_id: i.product_variant_id, quantity: Number(i.quantity) })),
      })
      setRecurringName('')
      setSuccess('تم حفظ السلة كطلبية متكررة')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ الطلبية المتكررة')
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">سلة المشتريات</h1>
          <p className="mt-1 text-muted">راجع عناصرك وأكمل الطلب</p>
        </div>
        {items.length > 0 && (
          <button
            onClick={clearCart}
            className="rounded-lg border border-danger text-danger px-4 py-2 text-sm font-medium hover:bg-danger-soft"
          >
            إفراغ السلة
          </button>
        )}
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {success && (
        <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">
          {success}
        </div>
      )}

      {items.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface p-8 text-center text-muted">
          السلة فارغة.
          <div className="mt-4 flex justify-center gap-2">
            <button
              onClick={() => navigate('/products')}
              className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
            >
              تسوق الآن
            </button>
            <button
              onClick={() => navigate('/recurring-carts')}
              className="rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-surface"
            >
              طلبياتي المتكررة
            </button>
          </div>
        </div>
      ) : (
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-4">
            {items.map((item) => {
              const variant = item.product_variant
              const product = variant?.product
              const lineTotal = (Number(item.quantity) || 0) * unitPrice(item)
              return (
                <div
                  key={item.id}
                  className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 shadow-sm sm:flex-row sm:items-center"
                >
                  <div className="flex-1">
                    <div className="font-semibold text-foreground">{product?.name || 'منتج'}</div>
                    <div className="text-sm text-muted">{variant?.name ? `الحجم: ${variant.name}` : ''}</div>
                    <div className="text-sm text-muted">{formatMoney(unitPrice(item))} د.ل / وحدة</div>
                  </div>
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => handleUpdate(item, (Number(item.quantity) || 0) - 1)}
                      disabled={updating === item.id || item.quantity <= 1}
                      className="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-surface disabled:opacity-50"
                    >
                      −
                    </button>
                    <span className="w-8 text-center text-sm font-medium">{item.quantity}</span>
                    <button
                      onClick={() => handleUpdate(item, (Number(item.quantity) || 0) + 1)}
                      disabled={updating === item.id}
                      className="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-surface disabled:opacity-50"
                    >
                      +
                    </button>
                  </div>
                  <div className="min-w-[6rem] text-end font-semibold text-foreground">
                    {formatMoney(lineTotal)} د.ل
                  </div>
                  <button
                    onClick={() => handleRemove(item)}
                    disabled={updating === item.id}
                    className="text-sm text-danger hover:underline disabled:opacity-50"
                  >
                    حذف
                  </button>
                </div>
              )
            })}
          </div>

          <div className="space-y-4">
            <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
              <h2 className="mb-4 font-semibold text-foreground">ملخص الطلب</h2>
              <label className="mb-1.5 block text-sm font-medium text-muted">عنوان التوصيل</label>
              {addresses.length === 0 ? (
                <div className="mb-4 text-sm text-danger">لا يوجد عنوان، أضف عنوانًا أولاً.</div>
              ) : (
                <select
                  value={addressId}
                  onChange={(e) => setAddressId(e.target.value)}
                  className="mb-4 w-full rounded-lg border border-border-strong bg-background px-3 py-2 text-sm text-foreground outline-none focus:border-primary"
                >
                  {addresses.map((a) => (
                    <option key={a.id} value={a.id}>{a.name}{a.city ? ` — ${a.city}` : ''}</option>
                  ))}
                </select>
              )}
              <div className="space-y-2 text-sm">
                <div className="flex justify-between text-muted">
                  <span>المجموع</span>
                  <span>{formatMoney(subtotal)} د.ل</span>
                </div>
                <div className="flex justify-between text-muted">
                  <span>التوصيل</span>
                  <span>{formatMoney(deliveryFee)} د.ل</span>
                </div>
                <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                  <span>الإجمالي</span>
                  <span>{formatMoney(total)} د.ل</span>
                </div>
              </div>
              <button
                onClick={handleCheckout}
                disabled={checkingOut || items.length === 0 || !addressId}
                className="mt-5 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
              >
                {checkingOut ? 'جاري إتمام الطلب...' : 'إتمام الطلب'}
              </button>
              <button
                onClick={() => navigate('/products')}
                className="mt-2 w-full rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-surface"
              >
                مواصلة التسوق
              </button>
            </div>

            <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
              <h2 className="mb-2 font-semibold text-foreground">حفظ كطلبية متكررة</h2>
              <p className="mb-3 text-sm text-muted">احفظ هذه السلة باسم لتطلبها مرة أخرى بضغطة واحدة.</p>
              <input
                value={recurringName}
                onChange={(e) => setRecurringName(e.target.value)}
                placeholder="مثال: الطلبية الأسبوعية"
                className="mb-2 w-full rounded-lg border border-border-strong bg-background px-3 py-2 text-sm text-foreground outline-none focus:border-primary"
              />
              <button
                onClick={handleSaveRecurring}
                disabled={saving || !recurringName.trim()}
                className="w-full rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5 disabled:opacity-60"
              >
                {saving ? 'جاري الحفظ...' : 'حفظ'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
