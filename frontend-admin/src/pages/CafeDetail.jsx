import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'
import { StatusBadge } from '../lib/status'

function formatMoney(value) {
  const num = Number(String(value ?? 0).replace(/,/g, ''))
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function CafeDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [cafe, setCafe] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/cafes/${id}`)
      setCafe(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات المقهى')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const branchColumns = [
    { key: 'name', label: 'الاسم' },
    { key: 'city', label: 'المدينة' },
    { key: 'street', label: 'الشارع' },
    { key: 'delivery_zone', label: 'منطقة التوصيل', render: (r) => r.delivery_zone?.name ?? '-' },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  const orderColumns = [
    { key: 'order_number', label: 'رقم الطلب' },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    { key: 'branch', label: 'الفرع', render: (r) => r.branch?.name ?? '-' },
    { key: 'status', label: 'الحالة', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'total_amount', label: 'الإجمالي', render: (r) => `${formatMoney(r.total_amount)} د.ل` },
    {
      key: 'order_date',
      label: 'التاريخ',
      render: (r) => (r.order_date ? new Date(r.order_date).toLocaleString('ar-LY') : '-'),
    },
  ]

  if (loading) return <PageSkeleton />
  if (!cafe) return <div className="text-danger">{error || 'المقهى غير موجود.'}</div>

  const branches = cafe.branches ?? []
  const recentOrders = cafe.recent_orders ?? []
  const stats = cafe.stats ?? {}
  const hasLocation = cafe.latitude != null && cafe.longitude != null

  const statCards = [
    { label: 'الفروع', value: stats.branches ?? 0 },
    { label: 'فروع نشطة', value: stats.active_branches ?? 0 },
    { label: 'المستخدمين', value: stats.users ?? 0 },
    { label: 'الطلبات', value: stats.orders ?? 0 },
    { label: 'إجمالي المشتريات', value: `${formatMoney(stats.orders_total)} د.ل` },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-4">
          {cafe.image_url ? (
            <img src={cafe.image_url} alt={cafe.name} className="h-14 w-14 rounded-xl border border-border object-cover" />
          ) : (
            <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-2xl font-extrabold text-primary">
              {(cafe.name ?? 'م').charAt(0)}
            </div>
          )}
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-extrabold text-foreground">{cafe.name || 'مقهى'}</h1>
              <Badge variant={cafe.is_active ? 'success' : 'default'}>{cafe.is_active ? 'نشط' : 'غير نشط'}</Badge>
            </div>
            <p className="mt-1 text-muted">{cafe.contact_info ?? 'بدون وسيلة تواصل'}</p>
          </div>
        </div>
        <Button variant="secondary" onClick={() => navigate('/cafes')}>العودة للقائمة</Button>
      </header>

      {error && <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        {statCards.map((s) => (
          <Card key={s.label}>
            <CardContent className="p-4">
              <div className="text-sm font-medium text-muted">{s.label}</div>
              <div className="mt-2 text-2xl font-extrabold text-foreground">{s.value}</div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>بيانات المقهى</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 text-sm text-foreground sm:grid-cols-2 lg:grid-cols-3">
            <div><span className="font-medium">الاسم:</span> {cafe.name ?? '-'}</div>
            <div><span className="font-medium">وسيلة التواصل:</span> {cafe.contact_info ?? '-'}</div>
            <div><span className="font-medium">العنوان:</span> {cafe.address ?? '-'}</div>
            <div><span className="font-medium">منشئ:</span> {cafe.created_by_admin?.name ?? 'تسجيل ذاتي'}</div>
            <div>
              <span className="font-medium">تاريخ التسجيل:</span>{' '}
              {cafe.created_at ? new Date(cafe.created_at).toLocaleDateString('ar-LY') : '-'}
            </div>
            <div>
              <span className="font-medium">الموقع:</span>{' '}
              {hasLocation ? (
                <a
                  href={`https://maps.google.com/?q=${cafe.latitude},${cafe.longitude}`}
                  target="_blank"
                  rel="noreferrer"
                  className="text-primary hover:underline"
                >
                  {Number(cafe.latitude).toFixed(5)}, {Number(cafe.longitude).toFixed(5)}
                </a>
              ) : 'غير محدد'}
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>فروع المقهى</CardTitle>
          <button
            onClick={() => navigate(`/cafe-branches?cafe_id=${cafe.id}`)}
            className="text-sm font-medium text-primary hover:underline"
          >
            عرض الكل
          </button>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={branchColumns}
            rows={branches}
            loading={false}
            emptyText="لا توجد فروع مسجلة لهذا المقهى."
            onRowClick={(row) => navigate(`/cafe-branches/${row.id}`)}
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>آخر الطلبات</CardTitle>
          <button
            onClick={() => navigate('/orders')}
            className="text-sm font-medium text-primary hover:underline"
          >
            عرض الكل
          </button>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={orderColumns}
            rows={recentOrders}
            loading={false}
            emptyText="لا توجد طلبات لهذا المقهى بعد."
            onRowClick={(row) => navigate(`/orders/${row.id}`)}
          />
        </CardContent>
      </Card>
    </>
  )
}
