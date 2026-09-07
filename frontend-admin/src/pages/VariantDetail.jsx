import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'
import { useModulePermission } from '../hooks/usePermission'

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const emptyForm = {
  attribute_value: '', price: '',
  sell_price: '', cost_price: '', barcode: '',
  status: '', manufacturing_year: '', expiry_date: '', is_active: true,
}

const STATUS_OPTIONS = ['متوفر', 'منخفض', 'نفد']

export default function VariantDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [variant, setVariant] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState(null)
  const [imageSaving, setImageSaving] = useState(false)
  const { canCreate: canCreateImage, canEdit: canEditImage, canDelete: canDeleteImage } =
    useModulePermission('PRODUCT_IMAGES')

  const load = async () => {
    setError('')
    try {
      const res = await client.get(`/product-variants/${id}`)
      const data = res.data?.data ?? res.data
      setVariant(data)
      setForm({
        attribute_value: data.attribute_value ?? '',
        price: data.price ?? '',
        sell_price: data.sell_price ?? '',
        cost_price: data.cost_price ?? '',
        barcode: data.barcode ?? '',
        status: data.status ?? '',
        manufacturing_year: data.manufacturing_year ?? '',
        expiry_date: data.expiry_date ? String(data.expiry_date).slice(0, 10) : '',
        is_active: data.is_active !== false,
      })
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات المتغير')
    }
  }

  useEffect(() => {
    setLoading(true)
    load().finally(() => setLoading(false))
  }, [id])

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    setMessage('')
    setError('')
    try {
      const ascii = (form.attribute_value || '')
        .trim()
        .replace(/[^A-Za-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toUpperCase()
      const pad = (n, w) => String(n).padStart(w, '0')
      const res = await client.put(`/product-variants/${id}`, {
        product_id: variant.product_id,
        sku: `PRD-${pad(variant.product_id, 4)}-${ascii || pad(variant.id, 5)}`,
        attribute_value: form.attribute_value || null,
        price: form.price !== '' ? Number(form.price) : 0,
        sell_price: form.sell_price !== '' ? Number(form.sell_price) : null,
        cost_price: form.cost_price !== '' ? Number(form.cost_price) : null,
        barcode: form.barcode || null,
        status: form.status || null,
        manufacturing_year: form.manufacturing_year !== '' ? Number(form.manufacturing_year) : null,
        expiry_date: form.expiry_date || null,
        is_active: form.is_active,
      })
      const data = res.data?.data ?? res.data
      setVariant((v) => ({ ...v, ...data }))
      setMessage('تم حفظ بيانات المتغير.')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الحفظ')
    } finally {
      setSaving(false)
    }
  }

  const uploadImage = async () => {
    if (!imageFile) return
    setImageSaving(true)
    setError('')
    try {
      const fd = new FormData()
      fd.append('product_variant_id', id)
      fd.append('image', imageFile)
      await client.postForm('/product-images', fd)
      setImageFile(null)
      setImagePreview(null)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل رفع الصورة')
    } finally {
      setImageSaving(false)
    }
  }

  const deleteImage = async (imageId) => {
    try {
      await client.delete(`/product-images/${imageId}`)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف الصورة')
    }
  }

  const setPrimary = async (imageId) => {
    try {
      for (const img of variant.images || []) {
        if (img.id !== imageId && img.is_primary) {
          await client.put(`/product-images/${img.id}`, {
            product_variant_id: id,
            is_primary: false,
          })
        }
      }
      await client.put(`/product-images/${imageId}`, {
        product_variant_id: id,
        is_primary: true,
      })
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تعيين الصورة الأساسية')
    }
  }

  const inventoryColumns = [
    { key: 'warehouse', label: 'المستودع', render: (r) => r.warehouse?.name ?? '-' },
    { key: 'quantity', label: 'الكمية' },
    {
      key: 'status',
      label: 'الحالة',
      render: (r) => (
        <Badge variant={Number(r.quantity) < 10 ? 'danger' : 'success'}>
          {Number(r.quantity) < 10 ? 'منخفض' : 'متوفر'}
        </Badge>
      ),
    },
  ]

  if (loading) return <PageSkeleton />
  if (!variant) return <div className="text-danger">{error || 'المتغير غير موجود.'}</div>

  const images = variant.images || []
  const totalStock = (variant.inventories || []).reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المتغير</h1>
          <p className="mt-1 text-muted">
            {variant.product?.name ?? 'منتج غير معروف'} — {variant.attribute_value || variant.sku || `#${variant.id}`}
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => navigate(`/products/${variant.product_id}`)}>
            صفحة المنتج
          </Button>
          <Button variant="secondary" onClick={() => navigate(-1)}>رجوع</Button>
        </div>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {message && <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{message}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>تعديل بيانات المتغير</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSave} className="space-y-4">
              <Input
                label="قيمة الخاصية (اكتبها بنفسك)"
                placeholder="مثال: صغير، كبير، 250ml"
                value={form.attribute_value}
                onChange={(e) => setForm({ ...form, attribute_value: e.target.value })}
              />
              <div className="grid gap-4 sm:grid-cols-3">
                <Input label="السعر الأساسي" type="number" min="0" step="0.01" value={form.price}
                  onChange={(e) => setForm({ ...form, price: e.target.value })} required />
                <Input label="سعر البيع" type="number" min="0" step="0.01" value={form.sell_price}
                  onChange={(e) => setForm({ ...form, sell_price: e.target.value })} />
                <Input label="سعر التكلفة" type="number" min="0" step="0.01" value={form.cost_price}
                  onChange={(e) => setForm({ ...form, cost_price: e.target.value })} />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="Barcode" value={form.barcode} onChange={(e) => setForm({ ...form, barcode: e.target.value })} />
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-muted">الكمية بالمخزن</label>
                  <div className="flex h-[38px] items-center rounded-md border border-border bg-background px-3 text-sm text-foreground">
                    {(variant.inventories || []).reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)}
                    <span className="mr-2 text-xs text-muted">(تُحدَّث من سجلات المخزون)</span>
                  </div>
                </div>
              </div>
              <div className="grid gap-4 sm:grid-cols-3">
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-muted">الحالة</label>
                  <select
                    value={form.status}
                    onChange={(e) => setForm({ ...form, status: e.target.value })}
                    className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                  >
                    <option value="">— بدون —</option>
                    {STATUS_OPTIONS.map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                </div>
                <Input label="سنة التصنيع" type="number" min="1900" max="2100" value={form.manufacturing_year}
                  onChange={(e) => setForm({ ...form, manufacturing_year: e.target.value })} />
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-muted">تاريخ انتهاء الصلاحية</label>
                  <input type="date" value={form.expiry_date}
                    onChange={(e) => setForm({ ...form, expiry_date: e.target.value })}
                    className="w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none" />
                </div>
              </div>
              <label className="flex items-center gap-2 text-sm text-foreground">
                <input type="checkbox" checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                  className="h-4 w-4 rounded border-border-strong" />
                نشط
              </label>
              <div className="flex justify-end">
                <Button type="submit" variant="primary" disabled={saving}>
                  {saving ? 'جاري الحفظ...' : 'حفظ التعديلات'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>صور المتغير</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4 flex flex-wrap gap-3">
                {images.length ? (
                  images.map((img) => (
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
                            <button onClick={() => setPrimary(img.id)}
                              className="rounded bg-white px-2 py-1 text-xs text-foreground">
                              أساسية
                            </button>
                          )}
                          {canDeleteImage && (
                            <button onClick={() => deleteImage(img.id)}
                              className="rounded bg-danger px-2 py-1 text-xs text-white">
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
                <div className="flex items-end gap-3">
                  <div className="flex-1">
                    <label className="mb-1.5 block text-sm font-medium text-muted">صورة جديدة</label>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => {
                        const file = e.target.files?.[0] || null
                        setImageFile(file)
                        setImagePreview(file ? URL.createObjectURL(file) : null)
                      }}
                      className="block w-full text-sm text-foreground file:ml-4 file:rounded file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                    />
                  </div>
                  {imagePreview && <img src={imagePreview} alt="" className="h-16 w-16 rounded-lg border border-border object-cover" />}
                  <Button variant="secondary" onClick={uploadImage} disabled={!imageFile || imageSaving}>
                    {imageSaving ? 'جاري الرفع...' : 'رفع'}
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>ملخص</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2 text-sm text-foreground">
                <div><span className="font-medium">SKU (تلقائي):</span> <code className="rounded bg-background px-1 text-xs" dir="ltr">{variant.sku || `PRD-${String(variant.product_id).padStart(4, '0')}-${String(variant.id).padStart(5, '0')}`}</code></div>
                <div><span className="font-medium">السعر الأساسي:</span> {formatMoney(variant.price ?? 0)} د.ل</div>
                {variant.sell_price != null && <div><span className="font-medium">سعر البيع:</span> {formatMoney(variant.sell_price)} د.ل</div>}
                <div><span className="font-medium">إجمالي المخزون:</span> {totalStock}</div>
                <div>
                  <span className="font-medium">الحالة:</span>{' '}
                  <Badge variant={variant.is_active !== false ? 'success' : 'default'}>
                    {variant.is_active !== false ? 'نشط' : 'معطل'}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>المخزون في المستودعات</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={inventoryColumns}
            rows={variant.inventories || []}
            loading={false}
            emptyText="لا يوجد مخزون مسجل لهذا المتغير."
          />
        </CardContent>
      </Card>
    </>
  )
}
