import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource, useApiList } from '../hooks/useApiResource'
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
  user_type_id: '',
  is_active: true,
}

export default function Users() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [filterActive, setFilterActive] = useState('')
  const userTypes = useApiList('/user-types?per_page=10000')
  const adminTypes = userTypes.filter((t) => ['admin', 'super_admin'].includes(t.name))
  const defaultAdminTypeId = adminTypes.find((t) => t.name === 'admin')?.id ?? adminTypes[0]?.id ?? ''

  const { items, loading, error, pagination, setPage, create, update, remove, confirmDialog } = useApiResource('/users', {
    search,
    user_type: 'admin,super_admin',
    is_active: filterActive,
  })
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('USERS')

  const openCreate = () => {
    setForm({ ...initial, user_type_id: defaultAdminTypeId })
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      password: '',
      user_type_id: item.user_type_id ?? defaultAdminTypeId,
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
      const data = { ...form, is_active: Boolean(form.is_active) }
      if (!data.mobile_number) data.mobile_number = null
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
    { key: 'user_type', label: 'النوع', render: (r) => <Badge variant="default">{r.user_type?.name ?? r.user_type_id}</Badge> },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الإدارة</h1>
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
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مدير</Button>}
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
        emptyText="لا يوجد مدراء."
        onRowClick={(row) => navigate(`/users/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل مدير' : 'إضافة مدير'} open={modal} onClose={close}>
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
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">النوع</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.user_type_id}
              onChange={(e) => setForm({ ...form, user_type_id: e.target.value })}
              required
            >
              <option value="">اختر النوع</option>
              {adminTypes.map((t) => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
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
