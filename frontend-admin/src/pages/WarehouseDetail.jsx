import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function variantLabel(v) {
  const productName = v?.product?.name ?? 'منتج غير معروف'
  const variantInfo = v?.attribute_value || v?.sku || `#${v?.id}`
  return `${productName} — ${variantInfo}`
}

export default function WarehouseDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [warehouse, setWarehouse] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const res = await client.get(`/warehouses/${id}`)
        setWarehouse(res.data?.data ?? res.data)
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل بيانات المستودع')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const inventoryColumns = [
    { key: 'product', label: 'المنتج / المتغير', render: (r) => variantLabel(r.product_variant) },
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
  if (!warehouse) return <div className="text-danger">{error || 'المستودع غير موجود.'}</div>

  const inventories = warehouse.inventories || []

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المستودع</h1>
          <p className="mt-1 text-muted">{warehouse.name || 'مستودع'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/warehouses')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات المستودع</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {warehouse.name ?? '-'}</div>
              <div><span className="font-medium">المدينة:</span> {warehouse.city ?? '-'}</div>
              <div><span className="font-medium">خط العرض:</span> {warehouse.latitude ?? '-'}</div>
              <div><span className="font-medium">خط الطول:</span> {warehouse.longitude ?? '-'}</div>
              <div>
                <span className="font-medium">عدد مناطق التوصيل:</span>{' '}
                {warehouse.delivery_zones?.length ?? warehouse.zones_count ?? 0}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ملخص المخزون</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">عدد الأصناف:</span> {inventories.length}</div>
              <div>
                <span className="font-medium">إجمالي الكمية:</span>{' '}
                {inventories.reduce((sum, i) => sum + (Number(i.quantity) || 0), 0)}
              </div>
              <div>
                <span className="font-medium">أصناف منخفضة (&lt;10):</span>{' '}
                {inventories.filter((i) => Number(i.quantity) < 10).length}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>المخزون داخل المستودع</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={inventoryColumns}
            rows={inventories}
            loading={false}
            emptyText="لا يوجد مخزون مسجل في هذا المستودع."
          />
        </CardContent>
      </Card>
    </>
  )
}
