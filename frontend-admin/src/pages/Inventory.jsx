import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import { useAuth } from '../context/AuthContext'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import SearchableSelect from '../components/ui/SearchableSelect'

const initial = { warehouse_id: '', product_variant_id: '', quantity: '' }

function variantLabel(v) {
  const productName = v.product?.name ?? 'منتج غير معروف'
  const variantInfo = v.attribute_value || v.sku || `#${v.id}`
  return `${productName} — ${variantInfo}`
}

export default function Inventory() {
  const [search, setSearch] = useState('')
  const { items, loading, error, pagination, setPage, create, update, remove } = useApiResource('/inventory', { search })
  const warehouses = useApiList('/warehouses')
  const variants = useApiList('/product-variants?per_page=10000')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('INVENTORY')
  const { hasFeature } = useAuth()

  const openCreate = () => {
    setForm({
      ...initial,
      warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
    })
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
    { key: 'updated_at', label: 'آخر تحديث', render: (r) => r.updated_at ? new Date(r.updated_at).toLocaleString('en-US') : '-' },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المخزون</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
          />
          {canCreate && hasFeature('add_inventory') && <Button variant="primary" onClick={openCreate}>إضافة مخزون</Button>}
        </div>
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
          {warehouses.length !== 1 && (
            <SearchableSelect
              label="المستودع"
              placeholder="اختر المستودع"
              searchPlaceholder="ابحث باسم المستودع..."
              options={warehouses}
              value={form.warehouse_id}
              onChange={(v) => setForm({ ...form, warehouse_id: v })}
              getLabel={(w) => w.name}
              required
            />
          )}
          <SearchableSelect
            label="المنتج / المتغير"
            placeholder="اختر المنتج"
            searchPlaceholder="ابحث باسم المنتج..."
            options={variants}
            value={form.product_variant_id}
            onChange={(v) => setForm({ ...form, product_variant_id: v })}
            getLabel={variantLabel}
            required
          />
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
