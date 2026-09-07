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

const initial = {
  name: '',
  email: '',
  mobile_number: '',
  password: '',
  latitude: '',
  longitude: '',
  is_available: false,
  is_active: true,
}

export default function Delegates() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [filterActive, setFilterActive] = useState('')
  const [filterAvailable, setFilterAvailable] = useState('')
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/delegates', { search, is_active: filterActive, is_available: filterAvailable })
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('DELEGATES')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      password: '',
      mobile_number: item.mobile_number ?? '',
      latitude: item.latitude ?? '',
      longitude: item.longitude ?? '',
      is_available: item.is_available ?? false,
    })
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
      const data = { ...form, is_active: Boolean(form.is_active), is_available: Boolean(form.is_available) }
      if (!data.mobile_number) data.mobile_number = null
      if (!data.latitude) data.latitude = null
      if (!data.longitude) data.longitude = null
      if (!data.password && editing) delete data.password
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'email', label: 'البريد الإلكتروني' },
    { key: 'mobile_number', label: 'الجوال' },
    {
      key: 'is_available',
      label: 'متاح',
      render: (r) => <Badge variant={r.is_available ? 'success' : 'default'}>{r.is_available ? 'نعم' : 'لا'}</Badge>,
    },
    {
      key: 'location',
      label: 'الموقع',
      render: (r) => (r.latitude && r.longitude ? `${r.latitude}, ${r.longitude}` : '-'),
    },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المناديب</h1>
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
          <FilterSelect
            label="التوفر"
            value={filterAvailable}
            onChange={setFilterAvailable}
            options={[
              { value: '1', label: 'متاح' },
              { value: '0', label: 'غير متاح' },
            ]}
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مندوب</Button>}
        </div>
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا يوجد مناديب."
        onRowClick={(row) => navigate(`/delegates/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل مندوب' : 'إضافة مندوب'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <Input
            label="البريد الإلكتروني"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            required
          />
          <Input
            label="الجوال"
            value={form.mobile_number || ''}
            onChange={(e) => setForm({ ...form, mobile_number: e.target.value })}
          />
          <Input
            label={<>كلمة المرور {editing && <span className="text-muted">(اتركه فارغًا للاحتفاظ بها)</span>}</>}
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required={!editing}
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="خط العرض"
              type="number"
              step="any"
              value={form.latitude}
              onChange={(e) => setForm({ ...form, latitude: e.target.value })}
            />
            <Input
              label="خط الطول"
              type="number"
              step="any"
              value={form.longitude}
              onChange={(e) => setForm({ ...form, longitude: e.target.value })}
            />
          </div>
          <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
              checked={form.is_available}
              onChange={(e) => setForm({ ...form, is_available: e.target.checked })}
            />
            متاح للتوصيل
          </label>
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
