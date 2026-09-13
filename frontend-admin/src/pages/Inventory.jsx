import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource, useApiList, usePremiumFeatureActive } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import client from '../api/client'

import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import SearchableSelect from '../components/ui/SearchableSelect'
import { FilterSelect } from '../components/ui/TableFilters'

const initial = {
  warehouse_id: '', product_variant_id: '', product_id: '', variant_name: '', quantity: '',
  cost_price: '', price: '', barcode: '',
}

function variantLabel(v) {
  const productName = v.product?.name ?? 'منتج غير معروف'
  const variantInfo = v.name || v.sku || `#${v.id}`
  return `${productName} — ${variantInfo}`
}

export default function Inventory() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [filterWarehouse, setFilterWarehouse] = useState('')
  const { items, loading, error, pagination, setPage, create, update, remove, confirmDialog } = useApiResource('/inventory', { search, warehouse_id: filterWarehouse })
  const warehouses = useApiList('/warehouses?per_page=10000')
  const variants = useApiList('/product-variants?per_page=10000')
  const products = useApiList('/products?per_page=10000')
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const [modalError, setModalError] = useState('')
  const [imageFiles, setImageFiles] = useState([])
  const [imagePreviews, setImagePreviews] = useState([])
  // ponytail: useApiList fetches once — track variants we create here so
  // name-matching keeps working without a refetch mechanism.
  const [createdVariants, setCreatedVariants] = useState([])
  const { canCreate, canEdit, canDelete } = useModulePermission('INVENTORY')
  const warehouseFeature = usePremiumFeatureActive('add_inventory')

  const variantById = (id) => variants.find((v) => String(v.id) === String(id))

  // ponytail: matching by (product, variant name) — the pair is the natural
  // key here; a real unique index would be the upgrade path.
  const findVariantByName = (productId, name) =>
    [...variants, ...createdVariants].find(
      (v) => String(v.product_id) === String(productId) && (v.name ?? '') === name.trim()
    )

  const handleImageFiles = (files) => {
    const list = Array.from(files || [])
    setImageFiles(list)
    setImagePreviews(list.map((f) => URL.createObjectURL(f)))
  }

  const fillVariantFields = (base, variantId) => {
    const v = variantById(variantId)
    if (!v) return base
    return {
      ...base,
      cost_price: v.cost_price ?? '',
      price: v.price ?? '',
      barcode: v.barcode ?? '',
    }
  }

  const openCreate = () => {
    setForm({
      ...initial,
      warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
    })
    setEditing(null)
    setModalError('')
    setImageFiles([])
    setImagePreviews([])
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
    setImageFiles([])
    setImagePreviews([])
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
    setModalError('')
    setImageFiles([])
    setImagePreviews([])
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      let variantId = form.product_variant_id
      if (!editing) {
        const name = form.variant_name.trim()
        const existing = findVariantByName(form.product_id, name)
        if (existing) {
          variantId = String(existing.id)
          await client.put(`/product-variants/${existing.id}`, {
            product_id: existing.product_id,
            sku: existing.sku,
            name: existing.name,
            price: form.price !== '' ? Number(form.price) : existing.price,
            cost_price: form.cost_price ? Number(form.cost_price) : null,
            barcode: form.barcode || null,
          })
        } else {
          const res = await client.post('/product-variants', {
            product_id: Number(form.product_id),
            name,
            price: form.price ? Number(form.price) : 0,
            cost_price: form.cost_price ? Number(form.cost_price) : null,
            barcode: form.barcode || null,
            is_active: true,
          })
          variantId = String(res.data?.data?.id ?? res.data?.id)
          setCreatedVariants((prev) => [...prev, res.data?.data ?? res.data])
        }
      } else {
        const v = variantById(variantId)
        if (v) {
          await client.put(`/product-variants/${v.id}`, {
            product_id: v.product_id,
            sku: v.sku,
            name: v.name,
            price: form.price !== '' ? Number(form.price) : v.price,
            cost_price: form.cost_price ? Number(form.cost_price) : null,
            barcode: form.barcode || null,
          })
        }
      }
      // POST adds stock; PUT sets the counted on-hand quantity (both recorded as stock movements).
      if (editing) await update(editing.id, { quantity: Number(form.quantity) })
      else await create({
        warehouse_id: Number(form.warehouse_id),
        product_variant_id: Number(variantId),
        quantity: Number(form.quantity),
      })
      for (const file of imageFiles) {
        const fd = new FormData()
        fd.append('product_variant_id', variantId)
        fd.append('image', file)
        await client.postForm('/product-images', fd)
      }
      close()
    } catch (err) {
      setModalError(err.response?.data?.message || 'فشل الحفظ')
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'product_variant', label: 'المنتج / المتغير', render: (r) => (r.product_variant ? (
      <button onClick={() => navigate(`/product-variants/${r.product_variant.id}`)} className="text-primary hover:underline">
        {variantLabel(r.product_variant)}
      </button>
    ) : '-') },
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
            className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          {warehouseFeature && (
            <FilterSelect
              label="المستودع"
              value={filterWarehouse}
              onChange={setFilterWarehouse}
              options={warehouses.map((w) => ({ value: w.id, label: w.name }))}
            />
          )}
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة مخزون</Button>}
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
          {editing ? (
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
          ) : (
            <>
              <SearchableSelect
                label="المنتج"
                placeholder="اختر المنتج"
                searchPlaceholder="ابحث باسم المنتج..."
                options={products}
                value={form.product_id}
                onChange={(v) => setForm({ ...form, product_id: v, variant_name: '' })}
                getLabel={(p) => p.name}
                required
              />
              <Input
                label="اسم المتغير"
                placeholder="مثال: صغير، كبير، 250ml — اكتب الاسم بنفسك"
                value={form.variant_name}
                onChange={(e) => {
                  const base = { ...form, variant_name: e.target.value }
                  const existing = findVariantByName(form.product_id, e.target.value)
                  setForm(existing ? fillVariantFields(base, existing.id) : base)
                }}
                required
              />
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">صور المتغير (اختياري)</label>
                <input
                  type="file"
                  accept="image/*"
                  multiple
                  onChange={(e) => handleImageFiles(e.target.files)}
                  className="block w-full text-sm text-foreground file:ml-4 file:rounded file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                />
                {imagePreviews.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-2">
                    {imagePreviews.map((url, i) => (
                      <img key={i} src={url} alt="" className="h-16 w-16 rounded-lg border border-border object-cover" />
                    ))}
                  </div>
                )}
              </div>
            </>
          )}
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
              value={form.price}
              onChange={(e) => setForm({ ...form, price: e.target.value })}
            />
          </div>
          <Input
            label="Barcode"
            value={form.barcode}
            onChange={(e) => setForm({ ...form, barcode: e.target.value })}
          />
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
