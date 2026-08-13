import { useState } from 'react'
import { useApiResource } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

const initial = { name: '', parent_category_id: '' }

export default function Categories() {
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/categories')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)

  const { canCreate, canEdit, canDelete } = useModulePermission('CATEGORIES')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({ ...initial, ...item, parent_category_id: item.parent_category_id ?? '' })
    setEditing(item)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const data = { ...form }
      if (!data.parent_category_id) data.parent_category_id = null
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'parent_category', label: 'التصنيف الأب', render: (r) => r.parent_category?.name ?? '-' },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">التصنيفات</h1>
        {canCreate && <Button variant="primary" onClick={openCreate}>إضافة تصنيف</Button>}
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد تصنيفات."
        actions={canEdit || canDelete ? (row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        ) : undefined}
      />
      <Modal title={editing ? 'تعديل تصنيف' : 'إضافة تصنيف'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">التصنيف الأب</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.parent_category_id}
              onChange={(e) => setForm({ ...form, parent_category_id: e.target.value })}
            >
              <option value="">لا يوجد</option>
              {items.filter((c) => c.id !== editing?.id).map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
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
