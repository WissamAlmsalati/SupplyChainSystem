import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import client from '../api/client'
import { useCart } from '../context/CartContext'
import FavoriteButton from '../components/FavoriteButton'

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

const LIST_KEYS = ['category_id', 'brand']
const FILTER_KEYS = ['search', 'category_id', 'brand', 'min_price', 'max_price', 'in_stock', 'favorites', 'sort', 'page']

// All filter state lives in the URL, so results are shareable and survive refresh.
function useFilterParams() {
  const [params, setParams] = useSearchParams()
  const get = (key) => params.get(key) ?? ''
  const getList = (key) => (params.get(key) ? params.get(key).split(',') : [])

  const update = (changes, { keepPage = false } = {}) => {
    const next = new URLSearchParams(params)
    Object.entries(changes).forEach(([key, value]) => {
      const empty = value === '' || value === null || value === undefined || value === false || (Array.isArray(value) && value.length === 0)
      if (empty) next.delete(key)
      else next.set(key, Array.isArray(value) ? value.join(',') : String(value))
    })
    if (!keepPage) next.delete('page')
    setParams(next)
  }

  // Legacy links used ?category=<id>
  useEffect(() => {
    if (params.get('category') && !params.get('category_id')) {
      const next = new URLSearchParams(params)
      next.set('category_id', params.get('category'))
      next.delete('category')
      setParams(next, { replace: true })
    }
  }, [])

  return { params, get, getList, update }
}

function FilterSection({ title, children }) {
  return (
    <div className="border-b border-border py-4 last:border-b-0">
      <div className="mb-2 text-sm font-bold text-foreground">{title}</div>
      {children}
    </div>
  )
}

export default function Products() {
  const navigate = useNavigate()
  const { addItem } = useCart()
  const { params, get, getList, update } = useFilterParams()
  const [products, setProducts] = useState([])
  const [meta, setMeta] = useState(null)
  const [facets, setFacets] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(null)
  const [showFilters, setShowFilters] = useState(false)
  const [priceDraft, setPriceDraft] = useState({ min: get('min_price'), max: get('max_price') })
  const [searchDraft, setSearchDraft] = useState(get('search'))

  const apiParams = useMemo(() => {
    const p = new URLSearchParams()
    FILTER_KEYS.forEach((key) => {
      const value = params.get(key)
      if (value) p.set(key === 'search' ? 'q' : key, value)
    })
    p.set('per_page', '24')
    return p.toString()
  }, [params])

  useEffect(() => {
    setPriceDraft({ min: get('min_price'), max: get('max_price') })
    setSearchDraft(get('search'))
    let cancelled = false
    async function load() {
      setLoading(true)
      setError('')
      try {
        const [productsRes, facetsRes] = await Promise.all([
          client.get(`/customer/products?${apiParams}`),
          client.get(`/customer/products/filters?${apiParams}`),
        ])
        if (cancelled) return
        setProducts(productsRes.data?.data ?? [])
        setMeta(productsRes.data?.meta ?? null)
        setFacets(facetsRes.data?.data ?? null)
      } catch (err) {
        if (!cancelled) setError(err.response?.data?.message || 'فشل تحميل المنتجات')
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    load()
    return () => { cancelled = true }
  }, [apiParams])

  const categories = facets?.categories ?? []
  const categoryName = (id) => categories.find((c) => String(c.id) === String(id))?.name ?? `#${id}`
  const toggleInList = (key, value) => {
    const current = getList(key)
    update({ [key]: current.includes(String(value)) ? current.filter((v) => v !== String(value)) : [...current, String(value)] })
  }

  const chips = [
    get('search') && { key: 'search', label: `بحث: ${get('search')}`, clear: { search: '' } },
    ...getList('category_id').map((id) => ({ key: `c${id}`, label: categoryName(id), clear: { category_id: getList('category_id').filter((v) => v !== id) } })),
    ...getList('brand').map((b) => ({ key: `b${b}`, label: b, clear: { brand: getList('brand').filter((v) => v !== b) } })),
    (get('min_price') || get('max_price')) && { key: 'price', label: `السعر: ${get('min_price') || '0'} – ${get('max_price') || '∞'} د.ل`, clear: { min_price: '', max_price: '' } },
    get('in_stock') && { key: 'stock', label: 'المتوفر فقط', clear: { in_stock: '' } },
    get('favorites') && { key: 'fav', label: 'المفضلة فقط', clear: { favorites: '' } },
  ].filter(Boolean)

  const clearAll = () => update(Object.fromEntries(FILTER_KEYS.filter((k) => k !== 'sort').map((k) => [k, ''])))

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

  const topCategories = categories.filter((c) => !c.parent_category_id || !categories.some((p) => p.id === c.parent_category_id))
  const childrenOf = (id) => categories.filter((c) => c.parent_category_id === id)

  const filtersPanel = (
    <div className="rounded-xl border border-border bg-surface px-4 shadow-sm">
      <FilterSection title="التصنيف">
        {categories.length === 0 ? <div className="text-sm text-muted">لا توجد تصنيفات</div> : (
          <ul className="space-y-1.5">
            {topCategories.map((c) => (
              <li key={c.id}>
                <label className="flex cursor-pointer items-center justify-between gap-2 text-sm text-foreground">
                  <span className="flex items-center gap-2">
                    <input type="checkbox" checked={getList('category_id').includes(String(c.id))} onChange={() => toggleInList('category_id', c.id)} />
                    {c.name}
                  </span>
                  <span className="text-xs text-muted">{c.count}</span>
                </label>
                {childrenOf(c.id).length > 0 && (
                  <ul className="ms-6 mt-1.5 space-y-1.5">
                    {childrenOf(c.id).map((child) => (
                      <li key={child.id}>
                        <label className="flex cursor-pointer items-center justify-between gap-2 text-sm text-foreground">
                          <span className="flex items-center gap-2">
                            <input type="checkbox" checked={getList('category_id').includes(String(child.id))} onChange={() => toggleInList('category_id', child.id)} />
                            {child.name}
                          </span>
                          <span className="text-xs text-muted">{child.count}</span>
                        </label>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>
        )}
      </FilterSection>

      {facets?.brands?.length > 0 && (
        <FilterSection title="العلامة التجارية">
          <ul className="max-h-48 space-y-1.5 overflow-y-auto">
            {facets.brands.map((b) => (
              <li key={b.name}>
                <label className="flex cursor-pointer items-center justify-between gap-2 text-sm text-foreground">
                  <span className="flex items-center gap-2">
                    <input type="checkbox" checked={getList('brand').includes(b.name)} onChange={() => toggleInList('brand', b.name)} />
                    {b.name}
                  </span>
                  <span className="text-xs text-muted">{b.count}</span>
                </label>
              </li>
            ))}
          </ul>
        </FilterSection>
      )}

      <FilterSection title="السعر (د.ل)">
        <form
          onSubmit={(e) => { e.preventDefault(); update({ min_price: priceDraft.min, max_price: priceDraft.max }) }}
          className="space-y-2"
        >
          <div className="flex items-center gap-2">
            <input type="number" min="0" step="0.01" inputMode="decimal" placeholder={facets?.price?.min != null ? `من ${facets.price.min}` : 'من'} value={priceDraft.min}
              onChange={(e) => setPriceDraft({ ...priceDraft, min: e.target.value })}
              className="w-full rounded-lg border border-border-strong bg-background px-2 py-1.5 text-sm outline-none focus:border-primary" />
            <span className="text-muted">–</span>
            <input type="number" min="0" step="0.01" inputMode="decimal" placeholder={facets?.price?.max != null ? `إلى ${facets.price.max}` : 'إلى'} value={priceDraft.max}
              onChange={(e) => setPriceDraft({ ...priceDraft, max: e.target.value })}
              className="w-full rounded-lg border border-border-strong bg-background px-2 py-1.5 text-sm outline-none focus:border-primary" />
          </div>
          <button type="submit" className="w-full rounded-lg border border-primary px-3 py-1.5 text-sm font-semibold text-primary hover:bg-primary/5">تطبيق</button>
        </form>
      </FilterSection>

      <FilterSection title="التوفر">
        <label className="flex cursor-pointer items-center justify-between gap-2 text-sm text-foreground">
          <span className="flex items-center gap-2">
            <input type="checkbox" checked={!!get('in_stock')} onChange={(e) => update({ in_stock: e.target.checked ? 1 : '' })} />
            المتوفر في المخزون فقط
          </span>
          {facets && <span className="text-xs text-muted">{facets.in_stock_count}</span>}
        </label>
        <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm text-foreground">
          <input type="checkbox" checked={!!get('favorites')} onChange={(e) => update({ favorites: e.target.checked ? 1 : '' })} />
          المفضلة فقط
        </label>
      </FilterSection>
    </div>
  )

  return (
    <>
      <header className="mb-4 flex flex-col gap-4 pt-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">
            {get('search') ? `نتائج البحث: "${get('search')}"` : getList('category_id').length === 1 ? categoryName(getList('category_id')[0]) : 'كل المنتجات'}
          </h1>
          <p className="mt-1 text-muted">{meta ? `${meta.total} منتج` : 'اختر منتجاتك وأضفها إلى السلة'}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <form onSubmit={(e) => { e.preventDefault(); update({ search: searchDraft.trim() }) }} className="flex">
            <input
              value={searchDraft}
              onChange={(e) => setSearchDraft(e.target.value)}
              placeholder="ابحث بالاسم، العلامة، الحجم أو الباركود..."
              className="w-64 rounded-s-lg border border-border-strong bg-background px-3 py-2 text-sm outline-none focus:border-primary"
            />
            <button type="submit" className="rounded-e-lg bg-primary px-4 text-sm font-semibold text-primary-foreground">بحث</button>
          </form>
          <select
            value={get('sort') || (get('search') ? 'relevance' : 'newest')}
            onChange={(e) => update({ sort: e.target.value })}
            className="rounded-lg border border-border-strong bg-background px-3 py-2 text-sm outline-none focus:border-primary"
            aria-label="الترتيب"
          >
            {(facets?.sort_options ?? []).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
          <button onClick={() => setShowFilters(!showFilters)} className="rounded-lg border border-border bg-background px-3 py-2 text-sm lg:hidden">
            الفلاتر{chips.length ? ` (${chips.length})` : ''}
          </button>
        </div>
      </header>

      {chips.length > 0 && (
        <div className="mb-4 flex flex-wrap items-center gap-2">
          {chips.map((chip) => (
            <button key={chip.key} onClick={() => update(chip.clear)} className="flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-sm text-primary hover:bg-primary/20">
              {chip.label} <span aria-hidden>✕</span>
            </button>
          ))}
          <button onClick={clearAll} className="text-sm text-muted underline hover:text-foreground">مسح الكل</button>
        </div>
      )}

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
        <aside className={`${showFilters ? 'block' : 'hidden'} lg:block`}>{filtersPanel}</aside>

        <div>
          {loading ? (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="rounded-xl border border-border bg-surface p-3 shadow-sm">
                  <div className="mb-3 aspect-square animate-pulse rounded-lg bg-border" />
                  <div className="mb-2 h-5 w-3/4 animate-pulse rounded-md bg-border" />
                  <div className="h-4 w-1/2 animate-pulse rounded-md bg-border" />
                </div>
              ))}
            </div>
          ) : products.length === 0 ? (
            <div className="rounded-xl border border-border bg-surface p-8 text-center text-muted">
              لا توجد منتجات مطابقة.
              {chips.length > 0 && <button onClick={clearAll} className="mt-3 block w-full text-sm text-primary hover:underline">مسح الفلاتر</button>}
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {products.map((p) => (
                <div
                  key={p.id}
                  onClick={() => navigate(`/products/${p.id}`)}
                  className="group cursor-pointer overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition hover:border-primary hover:shadow-md"
                >
                  <div className="relative aspect-square bg-background">
                    <FavoriteButton productId={p.id} className="absolute end-3 top-3 z-10" />
                    {p.image_url ? (
                      <img src={p.image_url} alt={p.name} className="h-full w-full object-cover transition group-hover:scale-105" />
                    ) : (
                      <div className="flex h-full items-center justify-center text-muted">لا توجد صورة</div>
                    )}
                    {!p.in_stock && <span className="absolute start-3 top-3 rounded-full bg-stone-800/80 px-2 py-0.5 text-xs text-white">غير متوفر</span>}
                    <button
                      onClick={(e) => handleAdd(p, e)}
                      disabled={adding === p.id || !p.in_stock}
                      aria-label="أضف إلى السلة"
                      className="absolute bottom-3 start-3 flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:bg-primary/90 disabled:opacity-40"
                    >
                      <PlusIcon className="h-5 w-5" />
                    </button>
                  </div>
                  <div className="p-4">
                    {p.brand && <div className="text-xs text-muted">{p.brand}</div>}
                    <h3 className="font-semibold text-foreground">{p.name}</h3>
                    {p.matched_variant && <div className="text-xs text-primary">الحجم: {p.matched_variant.name}</div>}
                    <div className="mt-2 text-lg font-bold text-primary">
                      {p.matched_variant
                        ? `${formatMoney(p.matched_variant.price)} د.ل`
                        : Number(p.max_price) > Number(p.min_price)
                          ? `${formatMoney(p.min_price)} – ${formatMoney(p.max_price)} د.ل`
                          : `${formatMoney(p.min_price)} د.ل`}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {meta && meta.last_page > 1 && (
            <div className="mt-6 flex items-center justify-center gap-3 text-sm">
              <button disabled={meta.current_page <= 1} onClick={() => update({ page: meta.current_page - 1 }, { keepPage: true })}
                className="rounded-lg border border-border bg-background px-4 py-2 disabled:opacity-40">السابق</button>
              <span className="text-muted">صفحة {meta.current_page} من {meta.last_page}</span>
              <button disabled={meta.current_page >= meta.last_page} onClick={() => update({ page: meta.current_page + 1 }, { keepPage: true })}
                className="rounded-lg border border-border bg-background px-4 py-2 disabled:opacity-40">التالي</button>
            </div>
          )}
        </div>
      </div>
    </>
  )
}
