import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import client from '../api/client'
import FavoriteButton from '../components/FavoriteButton'
import { useCart } from '../context/CartContext'

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

export default function Products() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { addItem, cart } = useCart()
  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [addresses, setAddresses] = useState([])
  const [categoryId, setCategoryId] = useState(searchParams.get('category') || '')
  const [search, setSearch] = useState(searchParams.get('search') || '')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(null)

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const params = new URLSearchParams()
        if (categoryId) params.set('category_id', categoryId)
        if (search.trim()) params.set('search', search.trim())
        const [productsRes, categoriesRes, addressesRes] = await Promise.all([
          client.get(`/cafe/products?${params.toString()}`),
          client.get('/cafe/categories'),
          client.get('/cafe/addresses'),
        ])
        setProducts(productsRes.data?.data ?? [])
        setCategories(categoriesRes.data?.data ?? [])
        setAddresses(addressesRes.data?.data?.addresses ?? [])
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل المنتجات')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [categoryId, search])

  const activeCategory = categories.find((c) => String(c.id) === categoryId)
  const handleAdd = async (product, e) => {
    e.stopPropagation()
    if (!product.default_variant_id) {
      setError('المنتج لا يحتوي على variant متاح.')
      return
    }
    setAdding(product.id)
    try {
      await addItem(product.default_variant_id, 1)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإضافة إلى السلة')
    } finally {
      setAdding(null)
    }
  }

  const filteredProducts = products

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">
            {search ? `نتائج البحث: "${search}"` : activeCategory?.name || 'كل المنتجات'}
          </h1>
          <p className="mt-1 text-muted">اختر منتجاتك وأضفها إلى السلة</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => { setCategoryId(''); setSearch('') }}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${
              !categoryId && !search ? 'bg-primary text-primary-foreground' : 'border border-border bg-background text-foreground hover:bg-surface'
            }`}
          >
            الكل
          </button>
          {categories.map((c) => (
            <button
              key={c.id}
              onClick={() => { setCategoryId(String(c.id)); setSearch('') }}
              className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${
                String(categoryId) === String(c.id) ? 'bg-primary text-primary-foreground' : 'border border-border bg-background text-foreground hover:bg-surface'
              }`}
            >
              {c.name}
            </button>
          ))}
        </div>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="rounded-xl border border-border bg-surface p-3 shadow-sm">
              <div className="mb-3 aspect-square animate-pulse rounded-lg bg-border" />
              <div className="mb-2 h-5 w-3/4 animate-pulse rounded-md bg-border" />
              <div className="h-4 w-1/2 animate-pulse rounded-md bg-border" />
            </div>
          ))}
        </div>
      ) : filteredProducts.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface p-8 text-center text-muted">
          لا توجد منتجات متاحة.
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {filteredProducts.map((p) => {
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
                    <div className="flex h-full items-center justify-center text-muted">
                      لا توجد صورة
                    </div>
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
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-lg font-bold text-primary">{formatMoney(price)} د.ل</span>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </>
  )
}
