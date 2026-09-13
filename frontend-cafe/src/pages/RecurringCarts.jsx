import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Named carts the customer re-orders from; the cart itself is kept after ordering.
export default function RecurringCarts() {
  const navigate = useNavigate()
  const [carts, setCarts] = useState([])
  const [addresses, setAddresses] = useState([])
  const [addressByCart, setAddressByCart] = useState({})
  const [payWithWallet, setPayWithWallet] = useState({})
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(null)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const load = async () => {
    setLoading(true)
    try {
      const [cartsRes, addressesRes] = await Promise.all([
        client.get('/cafe/recurring-carts'),
        client.get('/cafe/addresses'),
      ])
      setCarts(cartsRes.data?.data ?? [])
      setAddresses(addressesRes.data?.data?.addresses ?? [])
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الطلبيات المتكررة')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const defaultAddressId = () => String((addresses.find((a) => a.is_default) ?? addresses[0])?.id ?? '')

  const orderCart = async (cart) => {
    const addressId = addressByCart[cart.id] ?? defaultAddressId()
    if (!addressId) {
      setError('أضف عنوانًا أولاً')
      return
    }
    setBusy(cart.id)
    setError('')
    setSuccess('')
    try {
      const res = await client.post(`/cafe/recurring-carts/${cart.id}/order`, {
        address_id: Number(addressId),
        payment_method: payWithWallet[cart.id] ? 'wallet' : 'cash',
      })
      setSuccess(`تم إنشاء الطلب رقم ${res.data?.data?.order_number ?? ''}`)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إنشاء الطلب')
    } finally {
      setBusy(null)
    }
  }

  const deleteCart = async (cart) => {
    if (!window.confirm(`حذف "${cart.name}"؟`)) return
    setBusy(cart.id)
    try {
      await client.delete(`/cafe/recurring-carts/${cart.id}`)
      setCarts((prev) => prev.filter((c) => c.id !== cart.id))
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الحذف')
    } finally {
      setBusy(null)
    }
  }

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">الطلبيات المتكررة</h1>
        <p className="mt-1 text-muted">اطلب نفس السلة مرة أخرى بضغطة واحدة. تُحفظ من صفحة السلة.</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {success && <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{success}</div>}

      {loading ? (
        <div className="h-32 animate-pulse rounded-xl bg-border" />
      ) : carts.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface p-8 text-center text-muted">
          لا توجد طلبيات متكررة بعد.
          <button
            onClick={() => navigate('/cart')}
            className="mt-4 block w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 sm:mx-auto sm:w-auto"
          >
            الذهاب إلى السلة
          </button>
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {carts.map((cart) => (
            <div key={cart.id} className="rounded-xl border border-border bg-surface p-5 shadow-sm">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="font-semibold text-foreground">{cart.name}</h2>
                <span className="text-sm font-bold text-primary">{formatMoney(cart.subtotal)} د.ل</span>
              </div>
              <ul className="mb-4 space-y-1 text-sm text-muted">
                {(cart.items ?? []).map((item) => (
                  <li key={item.id} className="flex justify-between">
                    <span>
                      {item.product_variant?.product?.name ?? 'منتج'}
                      {item.product_variant?.name ? ` — ${item.product_variant.name}` : ''}
                    </span>
                    <span>× {item.quantity}</span>
                  </li>
                ))}
              </ul>
              <label className="mb-2 flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" checked={!!payWithWallet[cart.id]} onChange={(e) => setPayWithWallet((prev) => ({ ...prev, [cart.id]: e.target.checked }))} />
                الدفع من المحفظة (كامل المبلغ + التوصيل)
              </label>
              <div className="flex flex-col gap-2 sm:flex-row">
                <select
                  value={addressByCart[cart.id] ?? defaultAddressId()}
                  onChange={(e) => setAddressByCart((prev) => ({ ...prev, [cart.id]: e.target.value }))}
                  className="flex-1 rounded-lg border border-border-strong bg-background px-3 py-2 text-sm text-foreground outline-none focus:border-primary"
                >
                  {addresses.map((a) => (
                    <option key={a.id} value={a.id}>{a.name}</option>
                  ))}
                </select>
                <button
                  onClick={() => orderCart(cart)}
                  disabled={busy === cart.id}
                  className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                >
                  اطلب الآن
                </button>
                <button
                  onClick={() => deleteCart(cart)}
                  disabled={busy === cart.id}
                  className="rounded-lg border border-danger px-4 py-2 text-sm text-danger hover:bg-danger-soft disabled:opacity-60"
                >
                  حذف
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </>
  )
}
