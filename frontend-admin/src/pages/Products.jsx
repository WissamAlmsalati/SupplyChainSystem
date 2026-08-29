import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

const initial = { name: '', brand: '', description: '', category_id: '', tags: '' }

export default function Products() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/products', { search })
  const categories = useApiList('/categories')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState(null)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('PRODUCTS')

  const openCreate = () => {
    setForm(initial)
    setImageFile(null)
    setImagePreview(null)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      category_id: item.category_id ?? '',
      brand: item.brand ?? '',
      tags: Array.isArray(item.tags) ? item.tags.join(', ') : item.tags ?? '',
    })
    setImageFile(null)
    setImagePreview(item.image_url)
    setEditing(item)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setImageFile(null)
    setImagePreview(null)
    setEditing(null)
  }

  const handleImageChange = (e) => {
    const file = e.target.files[0]
    setImageFile(file || null)
    setImagePreview(file ? URL.createObjectURL(file) : null)
  }

  const buildFormData = () => {
    const data = new FormData()
    data.append('name', form.name)
    data.append('category_id', form.category_id)
    if (form.brand) data.append('brand', form.brand)
    if (form.description) data.append('description', form.description)
    if (form.tags) {
      form.tags
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean)
        .forEach((t) => data.append('tags[]', t))
    }
    if (imageFile) data.append('image', imageFile)
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
          <img src={r.image_url} alt="" className="h-10 w-10 rounded object-cover" />
        ) : (
          <span className="text-muted">-</span>
        ),
    },
    { key: 'name', label: 'الاسم' },
    { key: 'category', label: 'التصنيف', render: (r) => r.category?.name ?? '-' },
    { key: 'description', label: 'الوصف', render: (r) => r.description ? `${r.description.slice(0, 60)}...` : '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المنتجات</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة منتج</Button>}
        </div>
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد منتجات."
        onRowClick={(row) => navigate(`/products/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل منتج' : 'إضافة منتج'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <Input
            label="العلامة التجارية (Brand)"
            value={form.brand}
            onChange={(e) => setForm({ ...form, brand: e.target.value })}
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">التصنيف</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.category_id}
              onChange={(e) => setForm({ ...form, category_id: e.target.value })}
              required
            >
              <option value="">اختر التصنيف</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الوصف</label>
            <textarea
              rows={3}
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm transition-colors placeholder:text-muted focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.description || ''}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
          </div>
          <Input
            label="الوسوم (Tags) مفصولة بفاصلة"
            value={form.tags}
            onChange={(e) => setForm({ ...form, tags: e.target.value })}
            placeholder="مثال: قهوة, ساخن, مشروبات"
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الصورة</label>
            <input
              type="file"
              accept="image/*"
              onChange={handleImageChange}
              className="block w-full text-sm text-foreground file:mr-4 file:rounded file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
            />
            {imagePreview && (
              <img src={imagePreview} alt="Preview" className="mt-2 h-20 w-20 rounded object-cover" />
            )}
          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
