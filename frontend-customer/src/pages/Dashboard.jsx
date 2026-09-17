import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import FavoriteButton from '../components/FavoriteButton'
import { useCart } from '../context/CartContext'
import PromoBanner from '../components/PromoBanner'

function formatMoney(value) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function PlusIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
  )
}

export default function Dashboard() {
  const navigate = useNavigate()
  const { addItem, cart } = useCart()
  const [categories, setCategories] = useState([])
  const [products, setProducts] = useState([])
  const [addresses, setAddresses] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(null)
  const [sections, setSections] = useState([])

  useEffect(() => {
    async function load() {
      setLoading(true)
      try {
        const [categoriesRes, productsRes, addressesRes, sectionsRes] = await Promise.all([
          client.get('/customer/categories'),
          client.get('/customer/products'),
          client.get('/customer/addresses'),
          client.get('/customer/featured-sections').catch(() => ({ data: { data: [] } })),
        ])
        setSections(sectionsRes.data?.data ?? [])
        setCategories(categoriesRes.data?.data ?? [])
        setProducts(productsRes.data?.data ?? [])
        setAddresses(addressesRes.data?.data?.addresses ?? [])
      } catch (err) {
        setError(err.response?.data?.message || 'فشل التحميل')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  const featured = products.slice(0, 8)
  const renderCard = (p) => {
    const price = p.min_price ?? 0
    return (
      <div
        key={p.id}
        onClick={() => navigate(`/products/${p.id}`)}
        className="group cursor-pointer overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition hover:border-primary hover:shadow-md"
      >
        <div className="relative aspect-square bg-background">
                    <FavoriteButton productId={p.id} className="absolute end-3 top-3 z-10" />
          {p.image_url ? (
            <img
              src={p.image_url}
              alt={p.name}
              className="h-full w-full object-cover transition group-hover:scale-105"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-muted">لا توجد صورة</div>
          )}
          <button
            onClick={(e) => handleAdd(p, e)}
            disabled={adding === p.id}
            className="absolute bottom-3 start-3 flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:bg-primary/90 disabled:opacity-60"
          >
            <PlusIcon className="h-5 w-5" />
          </button>
        </div>
        <div className="p-4">
          <h3 className="font-semibold text-foreground">{p.name}</h3>
          <div className="mt-2 text-lg font-bold text-primary">{formatMoney(price)} د.ل</div>
        </div>
      </div>
    )
  }

  const handleAdd = async (product, e) => {
    e.stopPropagation()
    if (!product.default_variant_id) return
    setAdding(product.id)
    try {
      await addItem(product.default_variant_id, 1)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإضافة إلى السلة')
    } finally {
      setAdding(null)
    }
  }

  return (
    <div className="pt-6">
      {/* Hero */}
      <div className="relative overflow-hidden rounded-2xl bg-primary px-6 py-10 text-primary-foreground sm:px-10">
        <div className="relative z-10 max-w-xl">
          <h1 className="text-3xl font-extrabold sm:text-4xl">كل مستلزمات مقهاك في مكان واحد</h1>
          <p className="mt-3 text-primary-foreground/90">
            اطلب المنتجات بسهولة، تابع طلباتك، ودير عناوينك من تطبيق الساحل.
          </p>
          <button
            onClick={() => navigate('/products')}
            className="mt-5 rounded-lg bg-white px-6 py-2.5 text-sm font-bold text-primary hover:bg-white/90"
          >
            تسوق الآن
          </button>
        </div>
      </div>

      <PromoBanner />

      {error && (
        <div className="mb-4 mt-6 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* Categories */}
      <section className="mt-8">
        <h2 className="mb-4 text-xl font-bold text-foreground">تصفح حسب التصنيف</h2>
        {loading ? (
          <div className="flex gap-3 overflow-x-auto pb-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="h-10 w-28 flex-shrink-0 animate-pulse rounded-full bg-border" />
            ))}
          </div>
        ) : (
          <div className="flex flex-wrap gap-3">
            {categories.map((c) => (
              <button
                key={c.id}
                onClick={() => navigate(`/products?category=${c.id}`)}
                className="flex items-center gap-2 rounded-full border border-border bg-surface py-1.5 pe-5 ps-1.5 text-sm font-medium text-foreground transition hover:border-primary hover:text-primary"
              >
                <img src={c.image_url} alt="" className="h-8 w-8 rounded-full object-cover" />
                {c.name}
              </button>
            ))}
          </div>
        )}
      </section>

      {/* Featured products */}
      <section className="mt-8">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-xl font-bold text-foreground">منتجات مميزة</h2>
          <button
            onClick={() => navigate('/products')}
            className="text-sm font-medium text-primary hover:underline"
          >
            عرض الكل
          </button>
        </div>

        {loading ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="rounded-xl border border-border bg-surface p-3">
                <div className="mb-3 aspect-square animate-pulse rounded-lg bg-border" />
                <div className="mb-2 h-5 w-3/4 animate-pulse rounded-md bg-border" />
                <div className="h-4 w-1/2 animate-pulse rounded-md bg-border" />
              </div>
            ))}
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {featured.map(renderCard)}
          </div>
        )}
      </section>
      {/* Curated sections chosen by the business */}
      {sections.map((section) => (
        <section key={section.id} className="mt-10">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-xl font-bold text-foreground">{section.title}</h2>
            {section.has_more ? (
              <button onClick={() => navigate(`/sections/${section.id}`)} className="text-sm font-semibold text-primary hover:underline">
                عرض الكل ({section.products_total})
              </button>
            ) : (
              <span className="text-sm text-muted">{section.products_total} منتج</span>
            )}
          </div>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {section.products.map(renderCard)}
          </div>
        </section>
      ))}
    </div>
  )
}
