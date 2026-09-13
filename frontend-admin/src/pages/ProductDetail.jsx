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
import ConfirmDialog from '../components/ConfirmDialog'

function formatMoney(value) {
  const num = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''))
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

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
  const [confirmImageId, setConfirmImageId] = useState(null)
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
    setInventoryModal(variantId)
    setInventoryForm({
      warehouse_id: warehouses.length === 1 ? String(warehouses[0].id) : '',
      quantity: '',
    })
  }

  const closeInventoryModal = () => {
    setInventoryModal(null)
    setInventoryForm({ warehouse_id: '', quantity: '' })
  }

  const saveInventory = async () => {
    if (!inventoryForm.warehouse_id || !inventoryForm.quantity) return
    setSaving((prev) => ({ ...prev, inventory: true }))
    try {
      // add stock only — variant prices/barcodes stay exactly as they are
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

  const variants = product.variants || []
  const totalStock = variants.reduce(
    (sum, v) => sum + (v.inventories || []).reduce((a, i) => a + (Number(i.quantity) || 0), 0),
    0
  )
  const prices = variants.map((v) => Number(v.price)).filter((p) => !Number.isNaN(p))
  const minPrice = prices.length ? Math.min(...prices) : null
  const maxPrice = prices.length ? Math.max(...prices) : null

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-4">
          {product.image_url ? (
            <img
              src={product.image_url}
              alt={product.name}
              className="h-16 w-16 rounded-xl border border-border object-cover"
            />
          ) : (
            <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-background text-2xl font-bold text-primary">
              {product.name?.charAt(0) ?? '؟'}
            </div>
          )}
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-extrabold text-foreground">{product.name}</h1>
              {product.category && <Badge variant="primary">{product.category.name}</Badge>}
            </div>
            <p className="mt-1 text-sm text-muted">
              {variants.length} متغير · إجمالي المخزون: {totalStock}
              {minPrice != null &&
                ` · السعر: ${formatMoney(minPrice)}${maxPrice !== minPrice ? ` – ${formatMoney(maxPrice)}` : ''} د.ل`}
            </p>
          </div>
        </div>
        <Button variant="secondary" onClick={() => navigate('/products')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>بيانات المنتج</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-6 sm:flex-row">
            {product.image_url ? (
              <img
                src={product.image_url}
                alt={product.name}
                className="h-44 w-44 shrink-0 rounded-xl border border-border object-cover"
              />
            ) : (
              <div className="flex h-44 w-44 shrink-0 items-center justify-center rounded-xl bg-background text-5xl font-bold text-primary">
                {product.name?.charAt(0) ?? '؟'}
              </div>
            )}
            <div className="flex-1 space-y-4">
              <div>
                <span className="mb-1 block text-xs font-medium text-muted">الوصف</span>
                <p className="text-sm leading-relaxed text-foreground">{product.description || 'لا يوجد وصف لهذا المنتج.'}</p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <span className="mb-1 block text-xs font-medium text-muted">التصنيف</span>
                  <div className="text-sm font-medium text-foreground">{product.category?.name ?? '-'}</div>
                </div>
                {product.brand && (
                  <div>
                    <span className="mb-1 block text-xs font-medium text-muted">العلامة التجارية</span>
                    <div className="text-sm font-medium text-foreground">{product.brand}</div>
                  </div>
                )}
                <div>
                  <span className="mb-1 block text-xs font-medium text-muted">تاريخ الإنشاء</span>
                  <div className="text-sm text-foreground">
                    {product.created_at ? new Date(product.created_at).toLocaleDateString('en-GB') : '-'}
                  </div>
                </div>
                {product.tags?.length > 0 && (
                  <div className="sm:col-span-2">
                    <span className="mb-1 block text-xs font-medium text-muted">Tags</span>
                    <div className="flex flex-wrap gap-1">
                      {product.tags.map((t) => (
                        <Badge key={t} variant="default">{t}</Badge>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-bold text-foreground">المتغيرات ({variants.length})</h2>
        {variants.length > 0 && (
          <span className="text-sm text-muted">التعديل على المتغير من صفحته الخاصة</span>
        )}
      </div>

      {variants.length === 0 && (
        <div className="rounded-xl border border-dashed border-border-strong p-8 text-center text-sm text-muted">
          لا توجد متغيرات لهذا المنتج — يمكن إضافتها من صفحة إضافة مخزون.
        </div>
      )}

      <div className="grid items-start gap-4 lg:grid-cols-2">
        {variants.map((variant) => (
          <Card key={variant.id} className={variant.is_active === false ? 'opacity-70' : ''}>
            <CardHeader className="pb-3">
              <CardTitle className="flex flex-wrap items-center gap-2">
                <span className="text-base">{variant.name || variant.sku || `#${variant.id}`}</span>
                <code className="rounded bg-background px-1.5 py-0.5 text-xs text-muted" dir="ltr">
                  {variant.sku || '-'}
                </code>
                {variant.is_active === false && <Badge variant="default">معطل</Badge>}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {(() => {
                const invs = variant.inventories || []
                const total = invs.reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)
                return (
                  <>
                    <div className="mb-4 flex items-center justify-between rounded-lg bg-background px-4 py-3">
                      <div>
                        <span className="mb-0.5 block text-xs text-muted">السعر</span>
                        <span className="text-lg font-bold text-primary">
                          {formatMoney(variant.price ?? 0)} د.ل
                        </span>
                      </div>
                      <div className="text-end">
                        <span className="mb-0.5 block text-xs text-muted">المخزون</span>
                        {invs.length === 0 ? (
                          <span className="text-sm text-muted">غير مسجل</span>
                        ) : (
                          <Badge variant={total < 10 ? 'danger' : 'success'}>{total}</Badge>
                        )}
                      </div>
                    </div>
                    {invs.length > 0 && (
                      <div className="mb-4 text-xs leading-relaxed text-muted">
                        {invs.map((i) => `${i.warehouse?.name ?? 'مستودع'}: ${i.quantity}`).join(' · ')}
                      </div>
                    )}
                  </>
                )
              })()}
              <div className="mb-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-foreground lg:grid-cols-3">
                <div><span className="text-muted">Barcode:</span> {variant.barcode ?? '-'}</div>
                <div><span className="text-muted">التكلفة:</span> {variant.cost_price != null ? `${formatMoney(variant.cost_price)} د.ل` : '-'}</div>
                <div><span className="text-muted">الحالة:</span> {variant.is_active ? 'نشط' : 'غير نشط'}</div>
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
                              onClick={() => setConfirmImageId(img.id)}
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
            {product.variants.find((v) => v.id === inventoryModal)?.name ||
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
            {inventoryForm.warehouse_id && (
              <p className="mt-1.5 text-xs text-muted">
                المخزون الحالي في هذا المستودع:{' '}
                <span className="font-semibold text-foreground">
                  {product.variants
                    .find((v) => v.id === inventoryModal)
                    ?.inventories?.find((i) => String(i.warehouse_id) === inventoryForm.warehouse_id)
                    ?.quantity ?? 0}
                </span>
                {' '}— الكمية المضافة سيتم جمعها معه
              </p>
            )}
          </div>
          <Input
            label="الكمية"
            type="number"
            min="0"
            value={inventoryForm.quantity}
            onChange={(e) => setInventoryForm({ ...inventoryForm, quantity: e.target.value })}
          />
          <p className="text-xs text-muted">الأسعار والبيانات تبقى نفسها من بيانات المتغير — هذا النموذج لإضافة مخزون فقط.</p>
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

      <ConfirmDialog
        open={confirmImageId !== null}
        message="هل تريد حذف هذه الصورة؟ لا يمكن التراجع عن هذا الإجراء."
        onCancel={() => setConfirmImageId(null)}
        onConfirm={async () => {
          const imageId = confirmImageId
          setConfirmImageId(null)
          await deleteImage(imageId)
        }}
      />
    </>
  )
}
