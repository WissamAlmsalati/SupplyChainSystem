import { useState } from 'react'
import { useApiResource } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import MapPicker from '../components/MapPicker'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

const initial = { name: '', city: '', latitude: '', longitude: '' }

export default function Warehouses() {
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/warehouses')
  const [modal, setModal] = useState(false)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('WAREHOUSES')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({ ...initial, ...item, latitude: item.latitude ?? '', longitude: item.longitude ?? '' })
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
      if (data.latitude === '') data.latitude = null
      if (data.longitude === '') data.longitude = null
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'city', label: 'المدينة' },
    { key: 'latitude', label: 'خط العرض' },
    { key: 'longitude', label: 'خط الطول' },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المستودعات</h1>
        {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مستودع</Button>}
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد مستودعات."
        actions={canEdit || canDelete ? (row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        ) : undefined}
      />
      <Modal title={editing ? 'تعديل مستودع' : 'إضافة مستودع'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <Input
            label="المدينة"
            value={form.city || ''}
            onChange={(e) => setForm({ ...form, city: e.target.value })}
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الموقع</label>
            <div className="flex items-center gap-2">
              <Button type="button" variant="secondary" size="sm" onClick={() => setPickerOpen(true)}>
                {form.latitude && form.longitude ? 'تغيير على الخريطة' : 'اختيار على الخريطة'}
              </Button>
              <span className="text-sm text-muted">
                {form.latitude && form.longitude
                  ? `${Number(form.latitude).toFixed(5)}, ${Number(form.longitude).toFixed(5)}`
                  : 'لم يُختار موقع'}
              </span>
            </div>
          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
      <MapPicker
        open={pickerOpen}
        onClose={() => setPickerOpen(false)}
        initial={form.latitude && form.longitude ? { lat: Number(form.latitude), lng: Number(form.longitude) } : null}
        onSelect={({ lat, lng }) => setForm({ ...form, latitude: String(lat), longitude: String(lng) })}
      />
    </>
  )
}
