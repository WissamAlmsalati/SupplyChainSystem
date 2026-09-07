import { useEffect, useState } from 'react'
import { useApiResource } from '../hooks/useApiResource'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import SearchableSelect from '../components/ui/SearchableSelect'

const initial = { description: '', linkType: 'none', productId: '', productSearch: '', show_description: true, is_active: true }

const DESTINATIONS = [
  { value: 'none', label: 'بدون وجهة (صورة فقط)' },
  { value: 'home', label: 'الرئيسية' },
  { value: 'products', label: 'صفحة المنتجات' },
  { value: 'product', label: 'منتج محدد' },
  { value: 'orders', label: 'الطلبات' },
  { value: 'cart', label: 'سلة المشتريات' },
  { value: 'profile', label: 'الملف الشخصي' },
]

const LINK_BY_TYPE = { home: '/', products: '/products', orders: '/orders', cart: '/cart', profile: '/profile' }

function parseLink(link) {
  if (!link) return { linkType: 'none', productId: '' }
  const m = link.match(/^\/products\/(\d+)$/)
  if (m) return { linkType: 'product', productId: m[1] }
  const type = Object.keys(LINK_BY_TYPE).find((k) => LINK_BY_TYPE[k] === link)
  return { linkType: type ?? 'none', productId: '' }
}

const buildLink = (form) =>
  form.linkType === 'product' ? (form.productId ? `/products/${form.productId}` : '') : (LINK_BY_TYPE[form.linkType] ?? '')

export default function Promos() {
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/promos')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState(null)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const [products, setProducts] = useState([])
  const [productsLoading, setProductsLoading] = useState(false)

  useEffect(() => {
    if (form.linkType !== 'product') return
    const t = setTimeout(async () => {
      setProductsLoading(true)
      try {
        const { data } = await client.get('/products', { params: { search: form.productSearch || undefined, per_page: 50 } })
        setProducts(data?.data ?? data ?? [])
      } catch {
        setProducts([])
      } finally {
        setProductsLoading(false)
      }
    }, 300)
    return () => clearTimeout(t)
  }, [form.linkType, form.productSearch])

  const openCreate = () => {
    setForm(initial)
    setImageFile(null)
    setImagePreview(null)
    setEditing(null)
    setModal(true)
  }

  const openEdit = async (item) => {
    const parsed = parseLink(item.link)
    setForm({ ...initial, ...item, ...parsed, productSearch: '' })
    setImageFile(null)
    setImagePreview(item.image_url)
    setEditing(item)
    setModal(true)
    if (parsed.linkType === 'product') {
      try {
        const { data } = await client.get(`/products/${parsed.productId}`)
        const p = data?.data ?? data
        setForm((f) => ({ ...f, productSearch: p?.name ?? '' }))
      } catch {
        // ponytail: product deleted since promo created — keep the id, label shows the raw id
      }
    }
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setImageFile(null)
    setImagePreview(null)
    setEditing(null)
  }

  const buildFormData = () => {
    const data = new FormData()
    if (imageFile) data.append('image', imageFile)
    data.append('description', form.description ?? '')
    data.append('link', buildLink(form))
    data.append('show_description', form.show_description ? '1' : '0')
    data.append('is_active', form.is_active ? '1' : '0')
    return data
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const data = buildFormData()
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    {
      key: 'image_url',
      label: 'الصورة',
      render: (r) =>
        r.image_url ? (
          <img src={r.image_url} alt="" className="h-12 w-20 rounded object-cover" />
        ) : (
          <span className="text-muted">-</span>
        ),
    },
    {
      key: 'description',
      label: 'الوصف',
      render: (r) => (
        <span className="block max-w-xs truncate">{r.description || <span className="text-muted">-</span>}</span>
      ),
    },
    {
      key: 'link',
      label: 'الوجهة (Deep Link)',
      render: (r) =>
        r.link ? (
          <code className="rounded bg-background px-1.5 py-0.5 text-xs text-primary" dir="ltr">{r.link}</code>
        ) : (
          <span className="text-muted">-</span>
        ),
    },
    {
      key: 'show_description',
      label: 'عرض الوصف',
      render: (r) =>
        r.show_description ? (
          <Badge variant="primary">يظهر</Badge>
        ) : (
          <Badge variant="default">مخفي</Badge>
        ),
    },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => (
        <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'معطل'}</Badge>
      ),
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">البروموهات</h1>
        <Button variant="primary" onClick={openCreate}>إضافة برومو</Button>
      </header>

      {error && <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد بروموهات."
        actions={(row) => (
          <>
            <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>
            <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>
          </>
        )}
      />

      <Modal title={editing ? 'تعديل برومو' : 'إضافة برومو'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الصورة</label>
            <input
              type="file"
              accept="image/*"
              onChange={(e) => {
                const file = e.target.files?.[0]
                setImageFile(file || null)
                setImagePreview(file ? URL.createObjectURL(file) : null)
              }}
              className="w-full text-sm"
            />
            {imagePreview && (
              <img src={imagePreview} alt="" className="mt-2 h-28 w-full rounded-lg object-cover" />
            )}
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الوصف (يظهر في الموبايل)</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              rows={3}
              className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الوجهة (Deep Link)</label>
            <select
              value={form.linkType}
              onChange={(e) => setForm({ ...form, linkType: e.target.value, productId: '', productSearch: '' })}
              className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
            >
              {DESTINATIONS.map((d) => (
                <option key={d.value} value={d.value}>{d.label}</option>
              ))}
            </select>
            <p className="mt-1 text-xs text-muted">{buildLink(form) ? `اللينك: ${buildLink(form)}` : 'بدون لينك'}</p>
          </div>
          {form.linkType === 'product' && (
            <SearchableSelect
              label="اختر المنتج"
              placeholder="ابحث واختر منتج..."
              searchPlaceholder="ابحث بالاسم..."
              options={products}
              value={form.productId}
              onChange={(v) => setForm({ ...form, productId: v })}
              onQueryChange={(q) => setForm((f) => ({ ...f, productSearch: q }))}
              loading={productsLoading}
              getLabel={(p) => p.name}
            />
          )}
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input
              type="checkbox"
              checked={form.show_description}
              onChange={(e) => setForm({ ...form, show_description: e.target.checked })}
              className="h-4 w-4 rounded border-border-strong"
            />
            عرض الوصف في الموبايل
          </label>
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              className="h-4 w-4 rounded border-border-strong"
            />
            نشط (يظهر في الموبايل)
          </label>
          <div className="mt-6 flex items-center justify-end gap-2">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
