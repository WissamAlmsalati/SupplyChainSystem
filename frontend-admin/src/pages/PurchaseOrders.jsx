import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { StatusBadge } from '../lib/status'
import { FilterSelect } from '../components/ui/TableFilters'

const emptyItem = { product_variant_id: '', quantity: 1, unit_cost: '', expiry_date: '' }
const initial = { warehouse_id: '', note: '', items: [{ ...emptyItem }] }

const selectClass = 'w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none'

function variantLabel(v) {
  const productName = v.product?.name ?? 'منتج'
  return v.name ? `${productName} — ${v.name}` : productName
}

// Purchase orders bring stock into a warehouse: drafts are editable, "receive" adds the items to stock.
export default function PurchaseOrders() {
  const [filterWarehouse, setFilterWarehouse] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const { items, loading, error, pagination, setPage, fetch, create, update, remove, confirmDialog } = useApiResource('/purchase-orders', { warehouse_id: filterWarehouse, status: filterStatus })
  const warehouses = useApiList('/warehouses?per_page=10000')
  const variants = useApiList('/product-variants?per_page=10000')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const [modalError, setModalError] = useState('')
  const [actionError, setActionError] = useState('')
  const { canCreate, canEdit, canDelete } = useModulePermission('PURCHASE_ORDERS')

  const openCreate = () => {
    setForm({ ...initial, items: [{ ...emptyItem }] })
    setEditing(null)
    setModalError('')
    setModal(true)
  }

  const openEdit = async (row) => {
    setModalError('')
    const res = await client.get(`/purchase-orders/${row.id}`)
    const po = res.data?.data ?? res.data
    setForm({
      warehouse_id: po.warehouse_id ?? '',
      note: po.note ?? '',
      items: (po.items ?? []).length
        ? po.items.map((i) => ({
            product_variant_id: i.product_variant_id,
            quantity: i.quantity,
            unit_cost: i.unit_cost,
            expiry_date: i.expiry_date ? String(i.expiry_date).slice(0, 10) : '',
          }))
        : [{ ...emptyItem }],
    })
    setEditing(po)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
  }

  const setItem = (index, key, value) =>
    setForm((prev) => ({ ...prev, items: prev.items.map((it, i) => (i === index ? { ...it, [key]: value } : it)) }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setModalError('')
    try {
      const data = {
        warehouse_id: Number(form.warehouse_id),
        note: form.note || null,
        items: form.items
          .filter((it) => it.product_variant_id)
          .map((it) => ({
            product_variant_id: Number(it.product_variant_id),
            quantity: Number(it.quantity),
            unit_cost: Number(it.unit_cost) || 0,
            expiry_date: it.expiry_date || null,
          })),
      }
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } catch (err) {
      setModalError(err.response?.data?.message || 'فشل الحفظ')
    } finally {
      setSaving(false)
    }
  }

  const runAction = async (row, action, question) => {
    if (!window.confirm(question)) return
    setActionError('')
    try {
      await client.post(`/purchase-orders/${row.id}/${action}`)
      fetch()
    } catch (err) {
      setActionError(err.response?.data?.message || 'فشلت العملية')
    }
  }

  const columns = [
    { key: 'reference_number', label: 'المرجع' },
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'status', label: 'الحالة', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'items_count', label: 'الأصناف' },
    { key: 'created_by', label: 'أنشأه', render: (r) => r.created_by?.name ?? '-' },
    { key: 'received_at', label: 'تاريخ الاستلام', render: (r) => r.received_at ? new Date(r.received_at).toLocaleDateString('en-US') : '-' },
    { key: 'created_at', label: 'تاريخ الإنشاء', render: (r) => r.created_at ? new Date(r.created_at).toLocaleDateString('en-US') : '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">طلبات الشراء</h1>
        <div className="flex items-center gap-3">
          <FilterSelect
            label="المستودع"
            value={filterWarehouse}
            onChange={setFilterWarehouse}
            options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
          />
          <FilterSelect
            label="الحالة"
            value={filterStatus}
            onChange={setFilterStatus}
            options={[
              { value: 'draft', label: 'مسودة' },
              { value: 'received', label: 'مستلم' },
              { value: 'cancelled', label: 'ملغي' },
            ]}
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة طلب شراء</Button>}
        </div>
      </header>
      {(error || actionError) && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{actionError || error}</div>}
      {confirmDialog}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد طلبات شراء."
        actions={canEdit || canDelete ? (row) => (
          row.status === 'draft' ? (
            <>
              {canEdit && <Button variant="primary" size="sm" onClick={() => runAction(row, 'receive', 'استلام الطلب وإضافة الأصناف إلى المخزون؟')}>استلام</Button>}
              {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
              {canEdit && <Button variant="secondary" size="sm" onClick={() => runAction(row, 'cancel', 'إلغاء طلب الشراء؟')}>إلغاء</Button>}
              {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
            </>
          ) : row.status === 'cancelled' && canDelete ? (
            <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>
          ) : null
        ) : undefined}
      />
      <Modal title={editing ? `تعديل ${editing.reference_number}` : 'إضافة طلب شراء'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المستودع</label>
            <select className={selectClass} value={form.warehouse_id} onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })} required>
              <option value="">اختر المستودع</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </select>
          </div>
          <Input label="ملاحظة" value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />

          <div className="space-y-3">
            <div className="text-sm font-medium text-muted">الأصناف</div>
            {form.items.map((item, i) => (
              <div key={i} className="grid grid-cols-2 gap-2 rounded-lg border border-border p-3 sm:grid-cols-5">
                <select className={`${selectClass} col-span-2`} value={item.product_variant_id} onChange={(e) => setItem(i, 'product_variant_id', e.target.value)}>
                  <option value="">اختر الصنف</option>
                  {variants.map((v) => (
                    <option key={v.id} value={v.id}>{variantLabel(v)}</option>
                  ))}
                </select>
                <input className={selectClass} type="number" min="1" placeholder="الكمية" value={item.quantity} onChange={(e) => setItem(i, 'quantity', e.target.value)} />
                <input className={selectClass} type="number" min="0" step="0.01" placeholder="التكلفة" value={item.unit_cost} onChange={(e) => setItem(i, 'unit_cost', e.target.value)} />
                <div className="flex gap-1">
                  <input className={selectClass} type="date" title="تاريخ الانتهاء" value={item.expiry_date} onChange={(e) => setItem(i, 'expiry_date', e.target.value)} />
                  {form.items.length > 1 && (
                    <button type="button" className="px-2 text-danger" onClick={() => setForm((prev) => ({ ...prev, items: prev.items.filter((_, idx) => idx !== i) }))}>×</button>
                  )}
                </div>
              </div>
            ))}
            <Button type="button" variant="secondary" size="sm" onClick={() => setForm((prev) => ({ ...prev, items: [...prev.items, { ...emptyItem }] }))}>
              إضافة صنف
            </Button>
          </div>

          {modalError && <div className="text-sm text-danger">{modalError}</div>}
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
