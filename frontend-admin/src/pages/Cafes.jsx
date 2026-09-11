import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import Badge from '../components/ui/Badge'
import { FilterSelect } from '../components/ui/TableFilters'

const initial = { name: '', contact_info: '', is_active: true }

export default function Cafes() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [filterActive, setFilterActive] = useState('')
  const { items, loading, error, pagination, setPage, create, update, remove, confirmDialog } = useApiResource('/cafes', { search, is_active: filterActive })
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState(null)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('CAFES')

  const openCreate = () => {
    setForm(initial)
    setImageFile(null)
    setImagePreview(null)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({ ...initial, ...item })
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
    if (form.contact_info) data.append('contact_info', form.contact_info)
    data.append('is_active', form.is_active ? '1' : '0')
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
    { key: 'contact_info', label: 'وسيلة التواصل' },
    { key: 'created_by_admin', label: 'منشئ', render: (r) => r.created_by_admin?.name ?? '-' },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المقاهي</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <FilterSelect
            label="الحالة"
            value={filterActive}
            onChange={setFilterActive}
            options={[
              { value: '1', label: 'نشط' },
              { value: '0', label: 'معطل' },
            ]}
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مقهى</Button>}
        </div>
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {confirmDialog}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد مقاهي."
        onRowClick={(row) => navigate(`/cafes/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل مقهى' : 'إضافة مقهى'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <Input
            label="وسيلة التواصل"
            value={form.contact_info || ''}
            onChange={(e) => setForm({ ...form, contact_info: e.target.value })}
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
          <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            />
            نشط
          </label>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
