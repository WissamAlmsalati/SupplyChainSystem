import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import Badge from '../components/ui/Badge'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
  confirmed: 'مؤكد',
  shipped: 'تم الشحن',
  ordered: 'تم الطلب',
  received: 'مستلم',
}

const initial = { warehouse_id: '', order_date: '', status: 'pending' }

function statusVariant(status) {
  if (!status) return 'default'
  const s = String(status).toLowerCase()
  if (['completed', 'received'].includes(s)) return 'success'
  if (['cancelled'].includes(s)) return 'danger'
  if (['pending', 'ordered'].includes(s)) return 'warning'
  return 'default'
}

export default function PurchaseOrders() {
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/purchase-orders')
  const warehouses = useApiList('/warehouses')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('PURCHASE_ORDERS')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      warehouse_id: item.warehouse_id ?? '',
      order_date: item.order_date ?? '',
      status: item.status ?? 'pending',
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
      const data = { ...form }
      if (!data.order_date) data.order_date = null
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'id', label: 'الرقم', render: (r) => `#${r.id}` },
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'status', label: 'الحالة', render: (r) => <Badge variant={statusVariant(r.status)}>{statusLabels[r.status] || r.status || '-'}</Badge> },
    { key: 'order_date', label: 'تاريخ الطلب' },
    { key: 'created_at', label: 'تاريخ الإنشاء', render: (r) => r.created_at ? new Date(r.created_at).toLocaleDateString('en-US') : '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">طلبات الشراء</h1>
        {canCreate && <Button variant="primary" onClick={openCreate}>إضافة طلب شراء</Button>}
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد طلبات شراء."
        actions={canEdit || canDelete ? (row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        ) : undefined}
      />
      <Modal title={editing ? 'تعديل طلب شراء' : 'إضافة طلب شراء'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المستودع</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.warehouse_id}
              onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
              required
            >
              <option value="">اختر المستودع</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </select>
          </div>
          <Input
            label="الحالة"
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
            required
          />
          <Input
            label="تاريخ الطلب"
            type="date"
            value={form.order_date}
            onChange={(e) => setForm({ ...form, order_date: e.target.value })}
          />
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
