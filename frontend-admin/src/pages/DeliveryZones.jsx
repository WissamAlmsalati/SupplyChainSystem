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

const initial = { warehouse_id: '', hex_id: '', name: '', delivery_price: '', is_active: true }

export default function DeliveryZones() {
  const navigate = useNavigate()
  const [warehouseFilter, setWarehouseFilter] = useState('')
  const [filterActive, setFilterActive] = useState('')
  const [search, setSearch] = useState('')
  const warehouses = useApiList('/warehouses?per_page=10000')
  const path = warehouseFilter ? `/delivery-zones?warehouse_id=${warehouseFilter}` : '/delivery-zones'
  const { items, loading, error, pagination, setPage, create, update, remove, confirmDialog } = useApiResource(path, { search, is_active: filterActive })
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('DELIVERY_ZONES')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({ ...initial, ...item, delivery_price: item.delivery_price ?? '' })
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
      const data = {
        ...form,
        warehouse_id: form.warehouse_id === '' ? null : Number(form.warehouse_id),
        delivery_price: Number(form.delivery_price),
        is_active: Boolean(form.is_active),
      }
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'hex_id', label: 'السداسي', render: (r) => r.hex_id ?? '-' },
    { key: 'delivery_price', label: 'سعر التوصيل' },
    { key: 'latitude', label: 'خط العرض', render: (r) => r.latitude ?? '-' },
    { key: 'longitude', label: 'خط الطول', render: (r) => r.longitude ?? '-' },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">مناطق التوصيل</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <select
            className="rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
            value={warehouseFilter}
            onChange={(e) => setWarehouseFilter(e.target.value)}
          >
            <option value="">كل المستودعات</option>
            {warehouses.map((w) => (
              <option key={w.id} value={w.id}>{w.name}</option>
            ))}
          </select>
          <FilterSelect
            label="الحالة"
            value={filterActive}
            onChange={setFilterActive}
            options={[
              { value: '1', label: 'نشط' },
              { value: '0', label: 'معطل' },
            ]}
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة منطقة</Button>}
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
        emptyText="لا توجد مناطق توصيل."
        onRowClick={(row) => navigate(`/delivery-zones/${row.id}`)}
        actions={canEdit || canDelete ? (row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        ) : undefined}
      />
      <Modal title={editing ? 'تعديل منطقة' : 'إضافة منطقة'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المستودع</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.warehouse_id}
              onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
            >
              <option value="">بدون مستودع</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </select>
          </div>
          <Input
            label="معرف السداسي (hex_id)"
            value={form.hex_id || ''}
            onChange={(e) => setForm({ ...form, hex_id: e.target.value })}
            required
          />
          <Input
            label="الاسم"
            value={form.name || ''}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
          />
          <Input
            label="سعر التوصيل"
            type="number"
            step="0.01"
            min="0"
            value={form.delivery_price}
            onChange={(e) => setForm({ ...form, delivery_price: e.target.value })}
            required
          />
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
