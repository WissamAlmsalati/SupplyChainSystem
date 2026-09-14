import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useCart } from '../context/CartContext'
import { useFavorites } from '../context/FavoritesContext'
import FavoriteButton, { HeartIcon } from '../components/FavoriteButton'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Favorites() {
  const navigate = useNavigate()
  const { addItem } = useCart()
  const favorites = useFavorites()
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [adding, setAdding] = useState(null)
  const [meta, setMeta] = useState(null)
  const [loadingMore, setLoadingMore] = useState(false)

  const loadPage = async (page) => {
    const res = await client.get('/cafe/favorites', { params: { page, per_page: 20 } })
    setProducts((prev) => (page === 1 ? res.data?.data ?? [] : [...prev, ...(res.data?.data ?? [])]))
    setMeta(res.data?.meta ?? null)
  }

  useEffect(() => {
    loadPage(1).catch(() => setError('فشل تحميل المفضلة')).finally(() => setLoading(false))
  }, [])

  const loadMore = async () => {
    setLoadingMore(true)
    try {
      await loadPage(meta.current_page + 1)
    } catch {
      setError('فشل تحميل المزيد')
    } finally {
      setLoadingMore(false)
    }
  }

  // Hide cards as soon as their heart is turned off on this page.
  const visible = products.filter((p) => favorites?.isFavorite(p.id))

  const addToCart = async (product) => {
    if (!product.default_variant_id) return
    setAdding(product.id)
    setMessage('')
    try {
      await addItem(product.default_variant_id, 1)
      setMessage(`تمت إضافة ${product.name} إلى السلة`)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإضافة إلى السلة')
    } finally {
      setAdding(null)
    }
  }

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">المفضلة</h1>
        <p className="mt-1 text-muted">{meta ? `${meta.total} منتج محفوظ` : 'المنتجات التي حفظتها للرجوع إليها بسرعة'}</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {message && <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{message}</div>}

      {loading ? (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => <div key={i} className="aspect-[3/4] animate-pulse rounded-xl bg-border" />)}
        </div>
      ) : visible.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface p-10 text-center">
          <HeartIcon className="mx-auto h-10 w-10 text-muted" />
          <div className="mt-3 font-semibold text-foreground">لا توجد منتجات في المفضلة</div>
          <p className="mt-1 text-sm text-muted">اضغط على القلب في أي منتج لحفظه هنا.</p>
          <button onClick={() => navigate('/products')} className="mt-4 rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
            تصفح المنتجات
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {visible.map((p) => (
            <div
              key={p.id}
              onClick={() => navigate(`/products/${p.id}`)}
              className="group cursor-pointer overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition hover:border-primary hover:shadow-md"
            >
              <div className="relative aspect-square bg-background">
                {p.image_url ? (
                  <img src={p.image_url} alt={p.name} className="h-full w-full object-cover transition group-hover:scale-105" />
                ) : (
                  <div className="flex h-full items-center justify-center text-muted">لا توجد صورة</div>
                )}
                <FavoriteButton productId={p.id} className="absolute end-3 top-3" />
              </div>
              <div className="p-4">
                <h3 className="font-semibold text-foreground">{p.name}</h3>
                <div className="mt-1 text-lg font-bold text-primary">{formatMoney(p.min_price)} د.ل</div>
                <button
                  onClick={(e) => { e.stopPropagation(); addToCart(p) }}
                  disabled={adding === p.id}
                  className="mt-3 w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                >
                  {adding === p.id ? 'جاري الإضافة...' : 'أضف إلى السلة'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
      {!loading && meta && meta.current_page < meta.last_page && (
        <div className="mt-6 text-center">
          <button onClick={loadMore} disabled={loadingMore} className="rounded-lg border border-primary px-6 py-2 text-sm font-semibold text-primary hover:bg-primary/5 disabled:opacity-60">
            {loadingMore ? 'جاري التحميل...' : 'تحميل المزيد'}
          </button>
        </div>
      )}
    </>
  )
}
