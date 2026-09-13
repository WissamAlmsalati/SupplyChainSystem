import { useEffect, useMemo, useState } from 'react'
import { ArrowUp, ArrowDown, X, GripVertical, Plus } from 'lucide-react'
import client from '../api/client'
import { useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import Modal from '../components/Modal'
import { Card, CardContent } from '../components/ui/Card'

const emptyForm = { id: null, title: '', is_active: true, products: [] }

function move(list, index, delta) {
  const next = [...list]
  const target = index + delta
  if (target < 0 || target >= next.length) return list
  ;[next[index], next[target]] = [next[target], next[index]]
  return next
}

// Curated product rows shown at the bottom of the customer app home screen.
export default function FeaturedSections() {
  const { canCreate, canEdit, canDelete } = useModulePermission('FEATURED_SECTIONS')
  const allProducts = useApiList('/products?per_page=10000')
  const [sections, setSections] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [form, setForm] = useState(null)
  const [search, setSearch] = useState('')
  const [saving, setSaving] = useState(false)

  const load = async () => {
    const res = await client.get('/featured-sections')
    setSections(res.data?.data ?? [])
  }

  useEffect(() => {
    load().catch((err) => setError(err.response?.data?.message || 'فشل التحميل')).finally(() => setLoading(false))
  }, [])

  const openNew = () => {
    setSearch('')
    setForm({ ...emptyForm })
  }

  const openEdit = async (section) => {
    setSearch('')
    const res = await client.get(`/featured-sections/${section.id}`)
    const data = res.data?.data ?? res.data
    setForm({ id: data.id, title: data.title, is_active: data.is_active, products: data.products ?? [] })
  }

  const selectedIds = useMemo(() => new Set((form?.products ?? []).map((p) => p.id)), [form])
  const candidates = useMemo(() => {
    const term = search.trim()
    return allProducts
      .filter((p) => !selectedIds.has(p.id))
      .filter((p) => !term || p.name.includes(term) || (p.brand ?? '').includes(term))
      .slice(0, 30)
  }, [allProducts, selectedIds, search])

  const save = async (e) => {
    e.preventDefault()
    if (form.products.length === 0) {
      setError('اختر منتجاً واحداً على الأقل')
      return
    }
    setSaving(true)
    setError('')
    try {
      const payload = { title: form.title, is_active: form.is_active, product_ids: form.products.map((p) => p.id) }
      if (form.id) await client.put(`/featured-sections/${form.id}`, payload)
      else await client.post('/featured-sections', payload)
      setForm(null)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الحفظ')
    } finally {
      setSaving(false)
    }
  }

  const toggleActive = async (section) => {
    await client.put(`/featured-sections/${section.id}`, { is_active: !section.is_active })
    await load()
  }

  const destroy = async (section) => {
    if (!window.confirm(`حذف قسم "${section.title}"؟`)) return
    await client.delete(`/featured-sections/${section.id}`)
    await load()
  }

  const reorder = async (index, delta) => {
    const next = move(sections, index, delta)
    if (next === sections) return
    setSections(next)
    await client.post('/featured-sections/reorder', { ids: next.map((s) => s.id) })
  }

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">الأقسام المميزة</h1>
          <p className="mt-1 text-sm text-muted">منتجات مختارة تظهر في أسفل الصفحة الرئيسية لتطبيق المقاهي، بالترتيب المحدد هنا.</p>
        </div>
        {canCreate && <Button variant="primary" onClick={openNew}><Plus className="h-4 w-4" /> قسم جديد</Button>}
      </header>

      {error && !form && <div className="my-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mt-6 space-y-3">
        {loading && <div className="h-24 animate-pulse rounded-xl bg-border" />}
        {!loading && sections.length === 0 && (
          <Card><CardContent className="py-10 text-center text-muted">لا توجد أقسام بعد. أنشئ قسماً واختر منتجاته.</CardContent></Card>
        )}
        {sections.map((section, index) => (
          <Card key={section.id}>
            <CardContent className="flex flex-col gap-3 pt-5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-3">
                {canEdit && (
                  <div className="flex flex-col">
                    <button onClick={() => reorder(index, -1)} disabled={index === 0} className="rounded p-0.5 text-muted hover:bg-background disabled:opacity-30" aria-label="أعلى"><ArrowUp className="h-4 w-4" /></button>
                    <button onClick={() => reorder(index, 1)} disabled={index === sections.length - 1} className="rounded p-0.5 text-muted hover:bg-background disabled:opacity-30" aria-label="أسفل"><ArrowDown className="h-4 w-4" /></button>
                  </div>
                )}
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-lg font-bold text-foreground">{section.title}</span>
                    <Badge variant={section.is_active ? 'success' : 'default'}>{section.is_active ? 'ظاهر' : 'مخفي'}</Badge>
                  </div>
                  <div className="text-sm text-muted">{section.products_count} منتج · الترتيب {index + 1}</div>
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                {canEdit && <Button variant="secondary" size="sm" onClick={() => toggleActive(section)}>{section.is_active ? 'إخفاء' : 'إظهار'}</Button>}
                {canEdit && <Button variant="primary" size="sm" onClick={() => openEdit(section)}>تعديل المنتجات</Button>}
                {canDelete && <Button variant="danger" size="sm" onClick={() => destroy(section)}>حذف</Button>}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Modal title={form?.id ? 'تعديل القسم' : 'قسم جديد'} open={!!form} onClose={() => setForm(null)} size="lg">
        {form && (
          <form onSubmit={save} className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">عنوان القسم</label>
                <input
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  placeholder="مثال: الأكثر طلباً"
                  required
                  maxLength={150}
                  className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground focus:border-primary focus:outline-none"
                />
              </div>
              <label className="flex items-center gap-2 pb-2 text-sm text-foreground">
                <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                ظاهر في التطبيق
              </label>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <div className="mb-1.5 text-sm font-medium text-muted">المنتجات المختارة ({form.products.length})</div>
                <div className="min-h-[220px] rounded-lg border border-border bg-background p-2">
                  {form.products.length === 0 && <div className="p-6 text-center text-sm text-muted">أضف منتجات من القائمة</div>}
                  <ul className="space-y-1.5">
                    {form.products.map((p, i) => (
                      <li key={p.id} className="flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5 text-sm">
                        <GripVertical className="h-4 w-4 shrink-0 text-muted" />
                        <span className="w-5 text-center text-xs text-muted">{i + 1}</span>
                        {p.image_url ? <img src={p.image_url} alt="" className="h-8 w-8 rounded object-cover" /> : <div className="h-8 w-8 rounded bg-border" />}
                        <span className="flex-1 truncate font-medium text-foreground">
                          {p.name}
                          {(p.deleted_at || p.is_active === false) && <span className="ms-1 text-xs text-danger">(غير متاح)</span>}
                        </span>
                        <button type="button" onClick={() => setForm({ ...form, products: move(form.products, i, -1) })} disabled={i === 0} className="rounded p-1 text-muted hover:bg-background disabled:opacity-30" aria-label="أعلى"><ArrowUp className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={() => setForm({ ...form, products: move(form.products, i, 1) })} disabled={i === form.products.length - 1} className="rounded p-1 text-muted hover:bg-background disabled:opacity-30" aria-label="أسفل"><ArrowDown className="h-3.5 w-3.5" /></button>
                        <button type="button" onClick={() => setForm({ ...form, products: form.products.filter((x) => x.id !== p.id) })} className="rounded p-1 text-danger hover:bg-danger-soft" aria-label="إزالة"><X className="h-3.5 w-3.5" /></button>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              <div>
                <div className="mb-1.5 text-sm font-medium text-muted">إضافة منتجات</div>
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="ابحث باسم المنتج أو العلامة..."
                  className="mb-2 w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none"
                />
                <ul className="max-h-[260px] space-y-1 overflow-y-auto rounded-lg border border-border p-1">
                  {candidates.length === 0 && <li className="p-4 text-center text-sm text-muted">لا توجد نتائج</li>}
                  {candidates.map((p) => (
                    <li key={p.id}>
                      <button
                        type="button"
                        onClick={() => setForm({ ...form, products: [...form.products, p] })}
                        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-background"
                      >
                        {p.image_url ? <img src={p.image_url} alt="" className="h-8 w-8 rounded object-cover" /> : <div className="h-8 w-8 rounded bg-border" />}
                        <span className="flex-1">
                          <span className="font-medium text-foreground">{p.name}</span>
                          {p.category?.name && <span className="block text-xs text-muted">{p.category.name}</span>}
                        </span>
                        <Plus className="h-4 w-4 text-primary" />
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {error && <div className="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">{error}</div>}
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setForm(null)}>إلغاء</Button>
              <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ القسم'}</Button>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}
