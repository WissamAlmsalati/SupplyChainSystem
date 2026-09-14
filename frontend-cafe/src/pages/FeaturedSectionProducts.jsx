import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import client from '../api/client'
import { useCart } from '../context/CartContext'
import FavoriteButton from '../components/FavoriteButton'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// "View all" page of a home-screen section, loaded page by page.
export default function FeaturedSectionProducts() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { addItem } = useCart()
  const [section, setSection] = useState(null)
  const [products, setProducts] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(null)

  const load = async (page) => {
    setLoading(true)
    try {
      const res = await client.get(`/cafe/featured-sections/${id}/products`, { params: { page, per_page: 20 } })
      setSection(res.data?.section ?? null)
      setProducts((prev) => (page === 1 ? res.data?.data ?? [] : [...prev, ...(res.data?.data ?? [])]))
      setMeta(res.data?.meta ?? null)
    } catch (err) {
      setError(err.response?.status === 404 ? 'القسم غير متاح' : 'فشل التحميل')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load(1)
  }, [id])

  const add = async (p, e) => {
    e.stopPropagation()
    setAdding(p.id)
    try {
      await addItem(p.default_variant_id, 1)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإضافة إلى السلة')
    } finally {
      setAdding(null)
    }
  }

  return (
    <>
      <header className="mb-6 flex items-center gap-3 pt-6">
        <button onClick={() => navigate(-1)} className="rounded-lg border border-border bg-background px-3 py-1.5 text-sm hover:bg-surface">رجوع</button>
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">{section?.title ?? '...'}</h1>
          {meta && <p className="text-sm text-muted">{meta.total} منتج</p>}
        </div>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        {products.map((p) => (
          <div key={p.id} onClick={() => navigate(`/products/${p.id}`)}
            className="group cursor-pointer overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition hover:border-primary hover:shadow-md">
            <div className="relative aspect-square bg-background">
              <FavoriteButton productId={p.id} className="absolute end-3 top-3 z-10" />
              {p.image_url
                ? <img src={p.image_url} alt={p.name} className="h-full w-full object-cover transition group-hover:scale-105" />
                : <div className="flex h-full items-center justify-center text-muted">لا توجد صورة</div>}
              {!p.in_stock && <span className="absolute start-3 top-3 rounded-full bg-stone-800/80 px-2 py-0.5 text-xs text-white">غير متوفر</span>}
            </div>
            <div className="p-4">
              {p.brand && <div className="text-xs text-muted">{p.brand}</div>}
              <h3 className="font-semibold text-foreground">{p.name}</h3>
              <div className="mt-1 text-lg font-bold text-primary">{formatMoney(p.min_price)} د.ل</div>
              <button onClick={(e) => add(p, e)} disabled={adding === p.id || !p.in_stock}
                className="mt-3 w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                {adding === p.id ? 'جاري الإضافة...' : 'أضف إلى السلة'}
              </button>
            </div>
          </div>
        ))}
      </div>

      {loading && <div className="mt-4 h-24 animate-pulse rounded-xl bg-border" />}
      {!loading && meta && meta.current_page < meta.last_page && (
        <div className="mt-6 text-center">
          <button onClick={() => load(meta.current_page + 1)} className="rounded-lg border border-primary px-6 py-2 text-sm font-semibold text-primary hover:bg-primary/5">تحميل المزيد</button>
        </div>
      )}
    </>
  )
}
