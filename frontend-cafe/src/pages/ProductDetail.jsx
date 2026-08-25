import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useCart } from '../context/CartContext'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ProductDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { addItem } = useCart()
  const [product, setProduct] = useState(null)
  const [variants, setVariants] = useState([])
  const [branches, setBranches] = useState([])
  const [selectedVariant, setSelectedVariant] = useState(null)
  const [displayImage, setDisplayImage] = useState(null)
  const [quantity, setQuantity] = useState(1)
  const [branchId, setBranchId] = useState('')
  const [loading, setLoading] = useState(true)
  const [adding, setAdding] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const [productRes, variantsRes, branchesRes] = await Promise.all([
          client.get(`/cafe/products/${id}`),
          client.get(`/cafe/products/${id}/variants`),
          client.get('/cafe/branches'),
        ])
        const productData = productRes.data?.data ?? productRes.data
        const variantsData = variantsRes.data?.data ?? []
        const activeVariants = variantsData.filter((v) => v.is_active !== false)
        setProduct(productData)
        setVariants(activeVariants)
        setSelectedVariant(activeVariants[0] || null)
        const fallbackImage = productData.image_url || activeVariants[0]?.images?.map((img) => img.image_url).filter(Boolean)[0]
        setDisplayImage(fallbackImage || null)
        setBranches(branchesRes.data?.data ?? [])
        if (branchesRes.data?.data?.[0]) {
          setBranchId(String(branchesRes.data.data[0].id))
        }
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل تفاصيل المنتج')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const handleAddToCart = async () => {
    if (!selectedVariant) {
      setError('اختر variant أولاً')
      return
    }
    if (!branchId) {
      setError('اختر الفرع الذي تريد التوصيل إليه')
      return
    }
    setAdding(true)
    setError('')
    setMessage('')
    try {
      await addItem(Number(branchId), selectedVariant.id, Number(quantity))
      setMessage('تمت الإضافة إلى السلة')
      setTimeout(() => setMessage(''), 2000)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإضافة إلى السلة')
    } finally {
      setAdding(false)
    }
  }

  if (loading) {
    return (
      <div className="pt-6">
        <div className="h-8 w-48 animate-pulse rounded-md bg-border" />
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          <div className="aspect-square animate-pulse rounded-xl bg-border" />
          <div className="space-y-4">
            <div className="h-6 w-3/4 animate-pulse rounded-md bg-border" />
            <div className="h-4 w-full animate-pulse rounded-md bg-border" />
            <div className="h-4 w-2/3 animate-pulse rounded-md bg-border" />
          </div>
        </div>
      </div>
    )
  }

  const productImageUrl = product.image_url || product.variants?.flatMap((v) => v.images || [])[0]?.image_url

  const variantImages = selectedVariant?.images?.map((img) => img.image_url).filter(Boolean) || []

  if (!product) {
    return <div className="pt-6 text-danger">{error || 'المنتج غير موجود.'}</div>
  }

  return (
    <>
      <header className="mb-6 flex items-center gap-3 pt-6">
        <button
          onClick={() => navigate('/products')}
          className="rounded-lg border border-border bg-background px-3 py-1.5 text-sm hover:bg-surface"
        >
          رجوع
        </button>
        <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المنتج</h1>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {message && (
        <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">
          {message}
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="overflow-hidden rounded-xl border border-border bg-surface">
          {displayImage ? (
            <img src={displayImage} alt={product.name} className="h-full w-full object-cover" />
          ) : (
            <div className="flex aspect-square items-center justify-center text-muted">
              لا توجد صورة
            </div>
          )}
        </div>

        <div className="space-y-5">
          <div>
            <h2 className="text-2xl font-bold text-foreground">{product.name}</h2>
            <p className="mt-2 text-sm text-muted">{product.description || 'لا يوجد وصف.'}</p>
            <div className="mt-2 text-sm text-muted">التصنيف: {product.category?.name ?? '-'}</div>
          </div>

          <div>
            <label className="mb-2 block text-sm font-medium text-muted">اختر variant</label>
            <div className="flex flex-wrap gap-2">
              {variants.length === 0 ? (
                <div className="text-sm text-muted">لا توجد variants متاحة.</div>
              ) : (
                variants.map((v) => (
                  <button
                    key={v.id}
                    type="button"
                    onClick={() => {
                      setSelectedVariant(v)
                      const firstImage = v.images?.map((img) => img.image_url).filter(Boolean)[0]
                      setDisplayImage(firstImage || productImageUrl)
                    }}
                    className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                      selectedVariant?.id === v.id
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-foreground hover:bg-surface'
                    }`}
                  >
                    {v.attribute_value}
                    <span className="me-2 text-xs opacity-80">({formatMoney(v.price)} د.ل)</span>
                  </button>
                ))
              )}
            </div>
          </div>

          {selectedVariant && (
            <div className="text-lg font-semibold text-foreground">
              السعر: {formatMoney(selectedVariant.price)} د.ل
            </div>
          )}

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">الفرع</label>
              <select
                value={branchId}
                onChange={(e) => setBranchId(e.target.value)}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-sm text-foreground outline-none focus:border-primary"
              >
                <option value="">اختر فرع</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">الكمية</label>
              <input
                type="number"
                min={1}
                value={quantity}
                onChange={(e) => setQuantity(Math.max(1, Number(e.target.value) || 1))}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              />
            </div>
          </div>

          <div className="flex gap-3">
            <button
              onClick={handleAddToCart}
              disabled={adding || !selectedVariant || !branchId}
              className="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
              {adding ? 'جاري الإضافة...' : 'أضف إلى السلة'}
            </button>
            <button
              onClick={() => navigate('/cart')}
              className="rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium hover:bg-surface"
            >
              عرض السلة
            </button>
          </div>
        </div>
      </div>
    </>
  )
}
