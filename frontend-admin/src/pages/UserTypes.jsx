import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import { useAuth } from '../context/AuthContext'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import Badge from '../components/ui/Badge'

const initial = { name: '', permission_ids: [] }

function groupPermissions(permissions) {
  const groups = {}
  permissions.forEach((p) => {
    const parts = p.code.split('_')
    const op = parts.pop()?.toLowerCase()
    const module = parts.join('_').toLowerCase()
    if (!groups[module]) groups[module] = []
    groups[module].push({ ...p, op })
  })
  return groups
}

const operationLabels = {
  view: 'عرض',
  create: 'إضافة',
  edit: 'تعديل',
  delete: 'حذف',
  manage: 'إدارة',
  assign: 'تعيين',
}

const moduleLabels = {
  cafes: 'المقاهي',
  cafe_branches: 'فروع المقاهي',
  categories: 'التصنيفات',
  products: 'المنتجات',
  inventory: 'المخزون',
  orders: 'الطلبات',
  purchase_orders: 'طلبات الشراء',
  suppliers: 'الموردون',
  warehouses: 'المستودعات',
  delivery_zones: 'مناطق التوصيل',
  users: 'المستخدمين',
  user_types: 'أنواع المستخدمين',
  permissions: 'الصلاحيات',
  order: 'الطلبات',
  zone: 'المناطق',
}

function moduleName(module) {
  return moduleLabels[module] || module
}

function opName(op) {
  return operationLabels[op] || op
}

export default function UserTypes() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { items, loading, error, create, update, remove } = useApiResource('/user-types', { search })
  const permissions = useApiList('/permissions')
  const { canCreate, canEdit, canDelete } = useModulePermission('USER_TYPES')
  const { hasFeature } = useAuth()
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)

  const grouped = useMemo(() => groupPermissions(permissions), [permissions])

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      name: item.name,
      permission_ids: item.permissions?.map((p) => p.id) ?? [],
    })
    setEditing(item)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
  }

  const togglePermission = (id) => {
    setForm((prev) => {
      const ids = new Set(prev.permission_ids)
      if (ids.has(id)) ids.delete(id)
      else ids.add(id)
      return { ...prev, permission_ids: Array.from(ids) }
    })
  }

  const toggleModule = (modulePerms, checked) => {
    const ids = modulePerms.map((p) => p.id)
    setForm((prev) => {
      const current = new Set(prev.permission_ids)
      ids.forEach((id) => {
        if (checked) current.add(id)
        else current.delete(id)
      })
      return { ...prev, permission_ids: Array.from(current) }
    })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const data = { ...form }
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    {
      key: 'permissions',
      label: 'الصلاحيات',
      render: (r) => (
        <div className="flex flex-wrap gap-1">
          {r.permissions?.length ? (
            r.permissions.slice(0, 5).map((p) => (
              <Badge key={p.id} variant="primary">
                {p.code}
              </Badge>
            ))
          ) : (
            <span className="text-sm text-muted">لا توجد صلاحيات</span>
          )}
          {r.permissions?.length > 5 && (
            <Badge variant="default">+{r.permissions.length - 5}</Badge>
          )}
        </div>
      ),
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">أنواع المستخدمين (الأدوار)</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
          />
          {canCreate && hasFeature('add_role') && <Button variant="primary" onClick={openCreate}>إضافة دور</Button>}
        </div>
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        emptyText="لا توجد أدوار."
        onRowClick={(row) => navigate(`/user-types/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل دور' : 'إضافة دور'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="اسم الدور"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <div>
            <label className="mb-2 block text-sm font-medium text-muted">الصلاحيات</label>
            <div className="max-h-[300px] overflow-y-auto rounded-lg border border-border bg-background p-3">
              {Object.entries(grouped).length === 0 ? (
                <div className="text-sm text-muted">جاري تحميل الصلاحيات...</div>
              ) : (
                Object.entries(grouped).map(([module, modulePerms]) => {
                  const allSelected = modulePerms.every((p) => form.permission_ids.includes(p.id))
                  const someSelected = modulePerms.some((p) => form.permission_ids.includes(p.id)) && !allSelected
                  return (
                    <div key={module} className="mb-4 last:mb-0">
                      <div className="mb-2 flex items-center gap-2">
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
                          checked={allSelected}
                          ref={(el) => {
                            if (el) el.indeterminate = someSelected
                          }}
                          onChange={(e) => toggleModule(modulePerms, e.target.checked)}
                        />
                        <span className="font-semibold text-foreground">{moduleName(module)}</span>
                      </div>
                      <div className="me-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {modulePerms.map((p) => (
                          <label key={p.id} className="flex items-center gap-2 text-sm text-foreground">
                            <input
                              type="checkbox"
                              className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
                              checked={form.permission_ids.includes(p.id)}
                              onChange={() => togglePermission(p.id)}
                            />
                            {opName(p.op)}
                          </label>
                        ))}
                      </div>
                    </div>
                  )
                })
              )}
            </div>
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
