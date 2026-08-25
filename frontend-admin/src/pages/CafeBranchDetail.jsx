import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
  confirmed: 'مؤكد',
  shipped: 'تم الشحن',
}

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function CafeBranchDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [branch, setBranch] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/cafe-branches/${id}`)
      setBranch(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات الفرع')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const orderColumns = [
    { key: 'order_number', label: 'رقم الطلب', render: (r) => r.order_number ?? `#${r.id}` },
    { key: 'id', label: '#' },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    {
      key: 'status',
      label: 'الحالة',
      render: (r) => (
        <Badge variant={r.status === 'completed' || r.status === 'delivered' ? 'success' : 'warning'}>
          {statusLabels[r.status] || r.status}
        </Badge>
      ),
    },
    { key: 'total_amount', label: 'الإجمالي', render: (r) => `${formatMoney(r.total_amount)} د.ل` },
    { key: 'order_date', label: 'التاريخ', render: (r) => r.order_date ? new Date(r.order_date).toLocaleDateString('en-US') : '-' },
  ]

  if (loading) return <PageSkeleton />
  if (!branch) return <div className="text-danger">{error || 'الفرع غير موجود.'}</div>

  const orders = branch.orders ?? []

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الفرع</h1>
          <p className="mt-1 text-muted">{branch.name || 'فرع'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate(-1)}>العودة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات الفرع</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {branch.name ?? '-'}</div>
              <div><span className="font-medium">المقهى:</span> {branch.cafe?.name ?? '-'}</div>
              <div><span className="font-medium">المدينة:</span> {branch.city ?? '-'}</div>
              <div><span className="font-medium">الشارع:</span> {branch.street ?? '-'}</div>
              <div><span className="font-medium">منطقة التوصيل:</span> {branch.delivery_zone?.name ?? '-'}</div>
              <div>
                <span className="font-medium">الحالة:</span>{' '}
                <Badge variant={branch.is_active ? 'success' : 'default'}>{branch.is_active ? 'نشط' : 'غير نشط'}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ملخص الطلبيات</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">عدد الطلبيات:</span> {orders.length}</div>
              <div>
                <span className="font-medium">إجمالي قيمة الطلبيات:</span>{' '}
                {formatMoney(orders.reduce((sum, o) => sum + (Number(o.total_amount) || 0), 0))} د.ل
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>طلبيات الفرع</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={orderColumns}
            rows={orders}
            loading={false}
            emptyText="لا توجد طلبيات لهذا الفرع."
          />
        </CardContent>
      </Card>
    </>
  )
}
