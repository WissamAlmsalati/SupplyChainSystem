import { useEffect, useMemo, useState } from 'react'
import client from '../api/client'
import Modal from './Modal'
import Button from './ui/Button'
import Input from './ui/Input'

export default function QuickOrderModal({ open, onClose, onCreated }) {
  const [users, setUsers] = useState([])
  const [branches, setBranches] = useState([])
  const [variants, setVariants] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const [userId, setUserId] = useState('')
  const [branchId, setBranchId] = useState('')
  const [deliveryZoneId, setDeliveryZoneId] = useState('')
  const [deliveryFee, setDeliveryFee] = useState('0')
  const [items, setItems] = useState([{ product_variant_id: '', quantity: 1, unit_price: '' }])

  useEffect(() => {
    if (!open) return
    async function load() {
      try {
        const [uRes, bRes, vRes] = await Promise.all([
          client.get('/users?per_page=10000'),
          client.get('/cafe-branches?per_page=10000'),
          client.get('/product-variants?per_page=10000'),
        ])
        setUsers((uRes.data?.data ?? uRes.data ?? []).filter((u) => u.user_type?.name === 'cafe'))
        setBranches(bRes.data?.data ?? bRes.data ?? [])
        setVariants(vRes.data?.data ?? vRes.data ?? [])
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل البيانات')
      }
    }
    load()
  }, [open])

  const selectedUser = useMemo(() => users.find((u) => String(u.id) === userId), [users, userId])
  const availableBranches = useMemo(
    () => branches.filter((b) => b.cafe_id === selectedUser?.cafe_id),
    [branches, selectedUser]
  )
  const selectedBranch = useMemo(
    () => availableBranches.find((b) => String(b.id) === branchId),
    [availableBranches, branchId]
  )

  const updateItem = (i, field, value) => {
    setItems((prev) =>
      prev.map((item, idx) => {
        if (idx !== i) return item
        const next = { ...item, [field]: value }
        if (field === 'product_variant_id') {
          const variant = variants.find((v) => String(v.id) === value)
          next.unit_price = variant?.price ?? ''
        }
        return next
      })
    )
  }

  const addItem = () => setItems((prev) => [...prev, { product_variant_id: '', quantity: 1, unit_price: '' }])
  const removeItem = (i) => setItems((prev) => prev.filter((_, idx) => idx !== i))

  const totalAmount = useMemo(() => {
    const itemsTotal = items.reduce((sum, it) => sum + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0)
    return itemsTotal + (Number(deliveryFee) || 0)
  }, [items, deliveryFee])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    if (!userId || !branchId) {
      setError('يرجى اختيار المقهى والفرع.')
      return
    }
    if (items.length === 0 || items.some((it) => !it.product_variant_id || !it.quantity || !it.unit_price)) {
      setError('يرجى إكمال بيانات العناصر.')
      return
    }

    setLoading(true)
    try {
      const payload = {
        user_id: Number(userId),
        branch_id: Number(branchId),
        delivery_zone_id: deliveryZoneId ? Number(deliveryZoneId) : null,
        delivery_fee: Number(deliveryFee) || 0,
        status: 'pending',
        source: 'add order from dashboard',
        total_amount: totalAmount,
        items: items.map((it) => ({
          product_variant_id: Number(it.product_variant_id),
          quantity: Number(it.quantity),
          unit_price: Number(it.unit_price),
        })),
      }
      await client.post('/orders', payload)
      onCreated()
      handleClose()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إنشاء الطلب')
    } finally {
      setLoading(false)
    }
  }

  const handleClose = () => {
    setUserId('')
    setBranchId('')
    setDeliveryZoneId('')
    setDeliveryFee('0')
    setItems([{ product_variant_id: '', quantity: 1, unit_price: '' }])
    setError('')
    onClose()
  }

  const variantName = (v) => {
    const product = v.product?.name ?? 'منتج'
    return v.attribute_value ? `${product} - ${v.attribute_value}` : product
  }

  return (
    <Modal title="طلب سريع من الـ Dashboard" open={open} onClose={handleClose}>
      <form onSubmit={handleSubmit} className="max-h-[70vh] space-y-4 overflow-y-auto px-1">
        {error && (
          <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>
        )}

        <div>
          <label className="mb-1.5 block text-sm font-medium text-muted">حساب المقهى</label>
          <select
            className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
            value={userId}
            onChange={(e) => {
              setUserId(e.target.value)
              setBranchId('')
            }}
            required
          >
            <option value="">اختر مقهى</option>
            {users.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name} ({u.email})
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="mb-1.5 block text-sm font-medium text-muted">الفرع</label>
          <select
            className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
            value={branchId}
            onChange={(e) => {
              const id = e.target.value
              setBranchId(id)
              const branch = availableBranches.find((b) => String(b.id) === id)
              const zoneId = branch?.delivery_zone_id ?? ''
              setDeliveryZoneId(zoneId)
              setDeliveryFee(branch?.delivery_zone?.delivery_price ?? '0')
            }}
            required
            disabled={!userId}
          >
            <option value="">{userId ? 'اختر فرع' : 'اختر المقهى أولاً'}</option>
            {availableBranches.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name} - {b.city ?? 'بدون مدينة'}
              </option>
            ))}
          </select>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">منطقة التوصيل</label>
            <div className="rounded-md border border-border bg-background px-3.5 py-2 text-sm text-foreground">
              {selectedBranch?.delivery_zone?.name ?? 'بدون منطقة توصيل'}
            </div>
          </div>
          <Input
            label="رسوم التوصيل (د.ل)"
            type="number"
            step="0.01"
            min="0"
            value={deliveryFee}
            onChange={(e) => setDeliveryFee(e.target.value)}
          />
        </div>

        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <label className="text-sm font-medium text-muted">المنتجات</label>
            <Button type="button" variant="secondary" size="sm" onClick={addItem}>
              + إضافة منتج
            </Button>
          </div>
          {items.map((item, i) => (
            <div key={i} className="grid gap-2 rounded-lg border border-border bg-background p-3 sm:grid-cols-[1fr_auto_auto_auto]">
              <select
                className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
                value={item.product_variant_id}
                onChange={(e) => updateItem(i, 'product_variant_id', e.target.value)}
                required
              >
                <option value="">اختر منتج</option>
                {variants.map((v) => (
                  <option key={v.id} value={v.id}>
                    {variantName(v)} ({Number(v.price).toFixed(2)} د.ل)
                  </option>
                ))}
              </select>
              <Input
                type="number"
                min="1"
                value={item.quantity}
                onChange={(e) => updateItem(i, 'quantity', e.target.value)}
                className="min-w-[5rem]"
              />
              <Input
                type="number"
                step="0.01"
                min="0"
                value={item.unit_price}
                onChange={(e) => updateItem(i, 'unit_price', e.target.value)}
                placeholder="السعر"
                className="min-w-[6rem]"
              />
              <Button type="button" variant="danger" size="sm" onClick={() => removeItem(i)} disabled={items.length === 1}>
                حذف
              </Button>
            </div>
          ))}
        </div>

        <div className="flex items-center justify-between rounded-lg bg-primary-soft px-4 py-3 text-primary">
          <span className="font-medium">الإجمالي</span>
          <span className="text-lg font-bold">{totalAmount.toLocaleString('ar-SA', { minimumFractionDigits: 2 })} د.ل</span>
        </div>

        <div className="flex items-center justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={handleClose}>
            إلغاء
          </Button>
          <Button type="submit" variant="primary" disabled={loading}>
            {loading ? 'جاري الإنشاء...' : 'إنشاء الطلب'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
