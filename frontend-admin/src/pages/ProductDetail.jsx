import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useModulePermission } from '../hooks/usePermission'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'
import Modal from '../components/Modal'

export default function ProductDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [product, setProduct] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [imageFiles, setImageFiles] = useState({})
  const [imagePreviews, setImagePreviews] = useState({})
  const [saving, setSaving] = useState({})
  const [inventoryModal, setInventoryModal] = useState(null)
  const [warehouses, setWarehouses] = useState([])
  const [inventoryForm, setInventoryForm] = useState({ warehouse_id: '', quantity: '' })
  const { canCreate: canCreateImage, canEdit: canEditImage, canDelete: canDeleteImage } = useModulePermission('PRODUCT_IMAGES')
  const { canEdit: canEditProduct } = useModulePermission('PRODUCTS')
  const { canCreate: canCreateInventory } = useModulePermission('INVENTORY')

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const [res, whRes] = await Promise.all([
          client.get(`/products/${id}`),
          client.get('/warehouses?per_page=10000'),
        ])
        const data = res.data?.data ?? res.data
        setProduct(data)
        setWarehouses(whRes.data?.data ?? whRes.data ?? [])
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل بيانات المنتج')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const refresh = async () => {
    const res = await client.get(`/products/${id}`)
    setProduct(res.data?.data ?? res.data)
  }

  const handleFileChange = (variantId, file) => {
    setImageFiles((prev) => ({ ...prev, [variantId]: file }))
    setImagePreviews((prev) => ({
      ...prev,
      [variantId]: file ? URL.createObjectURL(file) : null,
    }))
  }

  const addImage = async (variantId) => {
    const file = imageFiles[variantId]
    if (!file) return
    setSaving((prev) => ({ ...prev, [variantId]: true }))
    try {
      const data = new FormData()
      data.append('product_variant_id', variantId)
      data.append('image', file)
      data.append('is_primary', '0')
      await client.postForm('/product-images', data)
      setImageFiles((prev) => ({ ...prev, [variantId]: null }))
      setImagePreviews((prev) => ({ ...prev, [variantId]: null }))
      await refresh()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إضافة الصورة')
    } finally {
      setSaving((prev) => ({ ...prev, [variantId]: false }))
    }
  }

  const deleteImage = async (imageId) => {
    try {
      await client.delete(`/product-images/${imageId}`)
      await refresh()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف الصورة')
    }
  }

  const openInventoryModal = (variantId) => {
    const v = product?.variants?.find((x) => x.id === variantId)
    setInventoryModal(variantId)
    setInventoryForm({
      warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
      quantity: '',
      cost_price: v?.cost_price ?? '',
      sell_price: v?.sell_price ?? '',
      barcode: v?.barcode ?? '',
      manufacturing_year: v?.manufacturing_year ?? '',
      expiry_date: v?.expiry_date ?? '',
    })
  }

  const closeInventoryModal = () => {
    setInventoryModal(null)
    setInventoryForm({
      warehouse_id: '', quantity: '', cost_price: '', sell_price: '', barcode: '', manufacturing_year: '', expiry_date: '',
    })
  }

  const saveInventory = async () => {
    if (!inventoryForm.warehouse_id || !inventoryForm.quantity) return
    setSaving((prev) => ({ ...prev, inventory: true }))
    try {
      if (canEditProduct) {
        await client.put(`/product-variants/${inventoryModal}`, {
          product_id: Number(id),
          sku: product.variants.find((v) => v.id === inventoryModal)?.sku,
          attribute_value: product.variants.find((v) => v.id === inventoryModal)?.attribute_value,
          price: product.variants.find((v) => v.id === inventoryModal)?.price,
          cost_price: inventoryForm.cost_price ? Number(inventoryForm.cost_price) : null,
          sell_price: inventoryForm.sell_price ? Number(inventoryForm.sell_price) : null,
          barcode: inventoryForm.barcode || null,
          manufacturing_year: inventoryForm.manufacturing_year ? Number(inventoryForm.manufacturing_year) : null,
          expiry_date: inventoryForm.expiry_date || null,
        })
      }
      await client.post('/inventory', {
        warehouse_id: Number(inventoryForm.warehouse_id),
        product_variant_id: inventoryModal,
        quantity: Number(inventoryForm.quantity),
      })
      await refresh()
      closeInventoryModal()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إضافة المخزون')
    } finally {
      setSaving((prev) => ({ ...prev, inventory: false }))
    }
  }

  const setPrimary = async (imageId, variantId) => {
    try {
      const variant = product.variants.find((v) => v.id === variantId)
      if (variant?.images) {
        for (const img of variant.images) {
          if (img.is_primary && img.id !== imageId) {
            await client.put(`/product-images/${img.id}`, { ...img, is_primary: false, product_variant_id: variantId })
          }
        }
      }
      const img = variant?.images?.find((i) => i.id === imageId)
      if (img) {
        await client.put(`/product-images/${imageId}`, { ...img, is_primary: true, product_variant_id: variantId })
      }
      await refresh()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تعيين الصورة الأساسية')
    }
  }

  if (loading) return <PageSkeleton />
  if (!product) return <div className="text-danger">{error || 'المنتج غير موجود.'}</div>

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المنتج</h1>
          <p className="mt-1 text-muted">{product.name}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/products')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>بيانات المنتج</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <span className="text-sm text-muted">التصنيف:</span>
              <div className="font-medium text-foreground">{product.category?.name ?? '-'}</div>
            </div>
            <div className="sm:col-span-2">
              <span className="text-sm text-muted">الوصف:</span>
              <div className="text-foreground">{product.description ?? '-'}</div>
            </div>
            {product.image_url && (
              <div className="sm:col-span-2">
                <span className="text-sm text-muted">صورة المنتج:</span>
                <img
                  src={product.image_url}
                  alt={product.name}
                  className="mt-2 h-40 w-40 rounded-lg border border-border object-cover"
                />
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <h2 className="mb-4 text-lg font-bold text-foreground">المتغيرات والصور</h2>

      <div className="grid gap-4">
        {product.variants?.length === 0 && (
          <div className="text-muted">لا توجد متغيرات لهذا المنتج.</div>
        )}

        {product.variants?.map((variant) => (
          <Card key={variant.id}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                المتغير: {variant.attribute_value || variant.sku || `#${variant.id}`}
                <Badge variant="default">SKU: {variant.sku || '-'}</Badge>
              </CardTitle>
            </CardHeader>
            <CardContent>
              {(() => {
                const invs = variant.inventories || []
                const total = invs.reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)
                return (
                  <div className="mb-4 flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-medium text-foreground">المخزون:</span>
                    {invs.length === 0 ? (
                      <span className="text-muted">لا يوجد مخزون مسجل.</span>
                    ) : (
                      <>
                        <Badge variant={total < 10 ? 'danger' : 'success'}>{total}</Badge>
                        <span className="text-muted">
                          ({invs.map((i) => `${i.warehouse?.name ?? 'مستودع'}: ${i.quantity}`).join('، ')})
                        </span>
                      </>
                    )}
                  </div>
                )
              })()}
              <div className="mb-4 grid gap-2 text-sm text-foreground sm:grid-cols-2 lg:grid-cols-4">
                <div><span className="font-medium">Barcode:</span> {variant.barcode ?? '-'}</div>
                <div><span className="font-medium">سعر البيع:</span> {variant.sell_price != null ? `${formatMoney(variant.sell_price)} د.ل` : '-'}</div>
                <div><span className="font-medium">سعر التكلفة:</span> {variant.cost_price != null ? `${formatMoney(variant.cost_price)} د.ل` : '-'}</div>
                <div><span className="font-medium">الحالة:</span> {variant.status ?? '-'}</div>
                <div><span className="font-medium">سنة التصنيع:</span> {variant.manufacturing_year ?? '-'}</div>
                <div><span className="font-medium">تاريخ الانتهاء:</span> {variant.expiry_date ? String(variant.expiry_date).slice(0, 10) : '-'}</div>
              </div>
              <div className="mb-4 flex justify-end gap-2">
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => navigate(`/product-variants/${variant.id}`)}
                >
                  تعديل المتغير
                </Button>
                {canCreateInventory && (
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => openInventoryModal(variant.id)}
                  >
                    إضافة مخزون
                  </Button>
                )}
              </div>

              <div className="mb-4 flex flex-wrap gap-3">
                {variant.images?.length ? (
                  variant.images.map((img) => (
                    <div key={img.id} className="group relative">
                      <img
                        src={img.image_url || img.url}
                        alt=""
                        className="h-24 w-24 rounded-lg border border-border object-cover"
                      />
                      {img.is_primary && (
                        <span className="absolute -top-2 -right-2 rounded-full bg-primary px-2 py-0.5 text-xs text-white">
                          أساسية
                        </span>
                      )}
                      {(canEditImage || canDeleteImage) && (
                        <div className="absolute inset-0 hidden items-center justify-center gap-1 rounded-lg bg-black/50 group-hover:flex">
                          {canEditImage && !img.is_primary && (
                            <button
                              onClick={() => setPrimary(img.id, variant.id)}
                              className="rounded bg-white px-2 py-1 text-xs text-foreground"
                            >
                              أساسية
                            </button>
                          )}
                          {canDeleteImage && (
                            <button
                              onClick={() => deleteImage(img.id)}
                              className="rounded bg-danger px-2 py-1 text-xs text-white"
                            >
                              حذف
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  ))
                ) : (
                  <div className="text-sm text-muted">لا توجد صور لهذا المتغير.</div>
                )}
              </div>

              {canCreateImage && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                  <div className="flex-1">
                    <label className="mb-1.5 block text-sm font-medium text-muted">صورة جديدة</label>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleFileChange(variant.id, e.target.files[0])}
                      className="block w-full text-sm text-foreground file:mr-4 file:rounded file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                    />
                  </div>
                  {imagePreviews[variant.id] && (
                    <img
                      src={imagePreviews[variant.id]}
                      alt=""
                      className="h-16 w-16 rounded-lg border border-border object-cover"
                    />
                  )}
                  <Button
                    variant="primary"
                    onClick={() => addImage(variant.id)}
                    disabled={saving[variant.id] || !imageFiles[variant.id]}
                  >
                    {saving[variant.id] ? 'جاري الإضافة...' : 'إضافة'}
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      <Modal title="إضافة مخزون" open={!!inventoryModal} onClose={closeInventoryModal}>
        <div className="space-y-4">
          <div className="rounded-md bg-surface px-3 py-2 text-sm text-foreground">
            <span className="text-muted">المتغير:</span>{' '}
            {product.variants.find((v) => v.id === inventoryModal)?.attribute_value ||
              product.variants.find((v) => v.id === inventoryModal)?.sku ||
              `#${inventoryModal}`}
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المستودع</label>
            <select
              value={inventoryForm.warehouse_id}
              onChange={(e) => setInventoryForm({ ...inventoryForm, warehouse_id: e.target.value })}
              className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
            >
              <option value="">اختر المستودع</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </select>
          </div>
          <Input
            label="الكمية"
            type="number"
            min="0"
            value={inventoryForm.quantity}
            onChange={(e) => setInventoryForm({ ...inventoryForm, quantity: e.target.value })}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="سعر التكلفة"
              type="number"
              min="0"
              step="0.01"
              value={inventoryForm.cost_price}
              onChange={(e) => setInventoryForm({ ...inventoryForm, cost_price: e.target.value })}
            />
            <Input
              label="سعر البيع"
              type="number"
              min="0"
              step="0.01"
              value={inventoryForm.sell_price}
              onChange={(e) => setInventoryForm({ ...inventoryForm, sell_price: e.target.value })}
            />
          </div>
          <Input
            label="Barcode"
            value={inventoryForm.barcode}
            onChange={(e) => setInventoryForm({ ...inventoryForm, barcode: e.target.value })}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="سنة التصنيع"
              type="number"
              min="1900"
              max="2100"
              value={inventoryForm.manufacturing_year}
              onChange={(e) => setInventoryForm({ ...inventoryForm, manufacturing_year: e.target.value })}
            />
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">تاريخ انتهاء الصلاحية</label>
              <input
                type="date"
                value={inventoryForm.expiry_date}
                onChange={(e) => setInventoryForm({ ...inventoryForm, expiry_date: e.target.value })}
                className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
              />
            </div>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={closeInventoryModal}>إلغاء</Button>
            <Button
              variant="primary"
              onClick={saveInventory}
              disabled={saving.inventory || !inventoryForm.warehouse_id || !inventoryForm.quantity}
            >
              {saving.inventory ? 'جاري الحفظ...' : 'حفظ'}
            </Button>
          </div>
        </div>
      </Modal>
    </>
  )
}
