import { useEffect, useState } from 'react'
import { formatDateTime } from '../lib/wallet'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import { StatusBadge } from '../lib/status'
import { PageSkeleton } from '../components/ui/Skeleton'

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function UserDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [user, setUser] = useState(null)
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const [userRes, logsRes] = await Promise.all([
        client.get(`/users/${id}`),
        client.get(`/activity-logs?user_id=${id}&per_page=10000`),
      ])
      setUser(userRes.data?.data ?? userRes.data)
      setLogs(logsRes.data?.data ?? logsRes.data?.data ?? [])
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات المستخدم')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const orderColumns = [
    { key: 'order_number', label: 'رقم الطلب', render: (r) => r.order_number ?? `#${r.id}` },
    { key: 'address', label: 'العنوان', render: (r) => r.delivery_address_name ?? '-' },
    {
      key: 'status',
      label: 'الحالة',
      render: (r) => <StatusBadge status={r.status} />,
    },
    { key: 'total_amount', label: 'الإجمالي', render: (r) => `${formatMoney(r.total_amount)} د.ل` },
    { key: 'placed_at', label: 'التاريخ', render: (r) => formatDateTime(r.placed_at) },
  ]

  const logColumns = [
    { key: 'description', label: 'الإجراء' },
    { key: 'entity_type', label: 'الكيان' },
    { key: 'action', label: 'النوع' },
    { key: 'created_at', label: 'التاريخ', render: (r) => r.created_at ? new Date(r.created_at).toLocaleString('en-US') : '-' },
  ]

  const addressColumns = [
    { key: 'name', label: 'الاسم' },
    { key: 'city', label: 'المدينة' },
    { key: 'street', label: 'الشارع' },
    {
      key: 'contact_phones',
      label: 'أرقام التواصل',
      render: (r) => (r.contact_phones?.length ? r.contact_phones.join(' / ') : '-'),
    },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'معطل'}</Badge>,
    },
  ]

  if (loading) return <PageSkeleton />
  if (!user) return <div className="text-danger">{error || 'المستخدم غير موجود.'}</div>

  const orders = user.orders ?? []

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المستخدم</h1>
          <p className="mt-1 text-muted">{user.name || 'مستخدم'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/users')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات المستخدم</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {user.name ?? '-'}</div>
              <div><span className="font-medium">البريد:</span> {user.email ?? '-'}</div>
              <div><span className="font-medium">الجوال:</span> {user.mobile_number ?? '-'}</div>
              <div><span className="font-medium">النوع:</span> <Badge variant="default">{user.user_type?.name ?? user.user_type_id}</Badge></div>
              <div>
                <span className="font-medium">الحالة:</span>{' '}
                <Badge variant={user.is_active ? 'success' : 'default'}>{user.is_active ? 'نشط' : 'غير نشط'}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ملخص الطلبات</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">عدد الطلبات:</span> {orders.length}</div>
              <div>
                <span className="font-medium">إجمالي قيمة الطلبات:</span>{' '}
                {formatMoney(orders.reduce((sum, o) => sum + (Number(o.total_amount) || 0), 0))} د.ل
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>العناوين</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={addressColumns}
            rows={user.addresses ?? []}
            loading={false}
            emptyText="لا توجد عناوين مسجلة لهذا المستخدم."
            onRowClick={(row) => navigate(`/addresses/${row.id}`)}
          />
        </CardContent>
      </Card>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>الطلبات</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={orderColumns}
            rows={orders}
            loading={false}
            emptyText="لا توجد طلبات لهذا المستخدم."
            onRowClick={(row) => navigate(`/orders/${row.id}`)}
          />
        </CardContent>
      </Card>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>سجل الأنشطة والإجراءات</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={logColumns}
            rows={logs}
            loading={false}
            emptyText="لا توجد إجراءات مسجلة لهذا المستخدم."
          />
        </CardContent>
      </Card>
    </>
  )
}
