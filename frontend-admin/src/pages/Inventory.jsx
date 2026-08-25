import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

const initial = { warehouse_id: '', product_variant_id: '', quantity: '' }

function variantLabel(v) {
  const productName = v.product?.name ?? 'منتج غير معروف'
  const variantInfo = v.attribute_value || v.sku || `#${v.id}`
  return `${productName} — ${variantInfo}`
}

export default function Inventory() {
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/inventory')
  const warehouses = useApiList('/warehouses')
  const variants = useApiList('/product-variants?per_page=10000')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('INVENTORY')

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      warehouse_id: item.warehouse_id ?? '',
      product_variant_id: item.product_variant_id ?? '',
      quantity: item.quantity ?? '',
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
      const data = { ...form, quantity: Number(form.quantity) }
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'product_variant', label: 'المنتج / المتغير', render: (r) => (r.product_variant ? variantLabel(r.product_variant) : '-') },
    { key: 'quantity', label: 'الكمية' },
    { key: 'updated_at', label: 'آخر تحديث', render: (r) => r.updated_at ? new Date(r.updated_at).toLocaleString('ar-SA') : '-' },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المخزون</h1>
        {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مخزون</Button>}
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد سجلات مخزون."
        actions={canEdit || canDelete ? (row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        ) : undefined}
      />
      <Modal title={editing ? 'تعديل مخزون' : 'إضافة مخزون'} open={modal} onClose={close}>
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
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المنتج / المتغير</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={form.product_variant_id}
              onChange={(e) => setForm({ ...form, product_variant_id: e.target.value })}
              required
            >
              <option value="">اختر المنتج</option>
              {variants.map((v) => (
                <option key={v.id} value={v.id}>{variantLabel(v)}</option>
              ))}
            </select>
          </div>
          <Input
            label="الكمية"
            type="number"
            min="0"
            value={form.quantity}
            onChange={(e) => setForm({ ...form, quantity: e.target.value })}
            required
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
