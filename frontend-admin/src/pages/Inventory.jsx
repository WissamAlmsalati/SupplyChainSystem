import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import client from '../api/client'

import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import SearchableSelect from '../components/ui/SearchableSelect'

const initial = {
  warehouse_id: '', product_variant_id: '', quantity: '',
  cost_price: '', sell_price: '', barcode: '', manufacturing_year: '', expiry_date: '',
}

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
  const [modalError, setModalError] = useState('')
  const { canCreate, canEdit, canDelete } = useModulePermission('INVENTORY')

  const variantById = (id) => variants.find((v) => String(v.id) === String(id))

  const fillVariantFields = (base, variantId) => {
    const v = variantById(variantId)
    if (!v) return base
    return {
      ...base,
      cost_price: v.cost_price ?? '',
      sell_price: v.sell_price ?? '',
      barcode: v.barcode ?? '',
      manufacturing_year: v.manufacturing_year ?? '',
      expiry_date: v.expiry_date ?? '',
    }
  }

  const openCreate = () => {
    setForm({
      ...initial,
      warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
    })
    setEditing(null)
    setModalError('')
    setModal(true)
  }

  const openEdit = (item) => {
    const base = {
      ...initial,
      warehouse_id: item.warehouse_id ?? '',
      product_variant_id: item.product_variant_id ?? '',
      quantity: item.quantity ?? '',
    }
    setForm(fillVariantFields(base, item.product_variant_id))
    setEditing(item)
    setModalError('')
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
    setModalError('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const v = variantById(form.product_variant_id)
      if (v) {
        await client.put(`/product-variants/${v.id}`, {
          product_id: v.product_id,
          sku: v.sku,
          attribute_value: v.attribute_value,
          price: v.price,
          cost_price: form.cost_price ? Number(form.cost_price) : null,
          sell_price: form.sell_price ? Number(form.sell_price) : null,
          barcode: form.barcode || null,
          manufacturing_year: form.manufacturing_year ? Number(form.manufacturing_year) : null,
          expiry_date: form.expiry_date || null,
        })
      }
      const data = {
        warehouse_id: Number(form.warehouse_id),
        product_variant_id: Number(form.product_variant_id),
        quantity: Number(form.quantity),
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

  const columns = [
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'product_variant', label: 'المنتج / المتغير', render: (r) => (r.product_variant ? variantLabel(r.product_variant) : '-') },
    { key: 'quantity', label: 'الكمية' },
    { key: 'updated_at', label: 'آخر تحديث', render: (r) => r.updated_at ? new Date(r.updated_at).toLocaleString('en-US') : '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المخزون</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مخزون</Button>}
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
            onChange={(v) => setForm(fillVariantFields({ ...form, product_variant_id: v }, v))}
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
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="سعر التكلفة"
              type="number"
              min="0"
              step="0.01"
              value={form.cost_price}
              onChange={(e) => setForm({ ...form, cost_price: e.target.value })}
            />
            <Input
              label="سعر البيع"
              type="number"
              min="0"
              step="0.01"
              value={form.sell_price}
              onChange={(e) => setForm({ ...form, sell_price: e.target.value })}
            />
          </div>
          <Input
            label="Barcode"
            value={form.barcode}
            onChange={(e) => setForm({ ...form, barcode: e.target.value })}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="سنة التصنيع"
              type="number"
              min="1900"
              max="2100"
              value={form.manufacturing_year}
              onChange={(e) => setForm({ ...form, manufacturing_year: e.target.value })}
            />
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">تاريخ انتهاء الصلاحية</label>
              <input
                type="date"
                value={form.expiry_date}
                onChange={(e) => setForm({ ...form, expiry_date: e.target.value })}
                className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
              />
            </div>
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
