import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useModulePermission } from '../hooks/usePermission'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

export default function ProductDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [product, setProduct] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [imageFiles, setImageFiles] = useState({})
  const [imagePreviews, setImagePreviews] = useState({})
  const [saving, setSaving] = useState({})
  const { canCreate: canCreateImage, canEdit: canEditImage, canDelete: canDeleteImage } = useModulePermission('PRODUCT_IMAGES')

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const res = await client.get(`/products/${id}`)
        const data = res.data?.data ?? res.data
        setProduct(data)
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
      <header className="mb-6 flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
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
            <div>
              <span className="text-sm text-muted">المورد:</span>
              <div className="font-medium text-foreground">{product.supplier?.name ?? '-'}</div>
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
    </>
  )
}
