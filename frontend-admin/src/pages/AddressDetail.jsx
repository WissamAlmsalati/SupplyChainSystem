import { useEffect, useState } from 'react'
import { formatDateTime } from '../lib/wallet'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import { StatusBadge } from '../lib/status'
import { PageSkeleton } from '../components/ui/Skeleton'

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function AddressDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [address, setAddress] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/addresses/${id}`)
      setAddress(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات العنوان')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const orderColumns = [
    { key: 'order_number', label: 'رقم الطلب', render: (r) => r.order_number ?? `#${r.id}` },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    {
      key: 'status',
      label: 'الحالة',
      render: (r) => <StatusBadge status={r.status} />,
    },
    { key: 'total_amount', label: 'الإجمالي', render: (r) => `${formatMoney(r.total_amount)} د.ل` },
    { key: 'placed_at', label: 'التاريخ', render: (r) => formatDateTime(r.placed_at) },
  ]

  if (loading) return <PageSkeleton />
  if (!address) return <div className="text-danger">{error || 'العنوان غير موجود.'}</div>

  const orders = address.orders ?? []

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل العنوان</h1>
          <p className="mt-1 text-muted">{address.name || 'عنوان'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate(-1)}>العودة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات العنوان</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {address.name ?? '-'}</div>
              <div><span className="font-medium">المستخدم:</span> {address.user?.name ?? '-'}</div>
              <div><span className="font-medium">المدينة:</span> {address.city ?? '-'}</div>
              <div><span className="font-medium">الشارع:</span> {address.street ?? '-'}</div>
              <div><span className="font-medium">أرقام التواصل:</span> {(address.contact_phones ?? []).join('، ') || '-'}</div>
              <div><span className="font-medium">منطقة التوصيل:</span> {address.delivery_zone?.name ?? '-'}</div>
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
          <CardTitle>طلبيات العنوان</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={orderColumns}
            rows={orders}
            loading={false}
            emptyText="لا توجد طلبيات لهذا العنوان."
            onRowClick={(row) => navigate(`/orders/${row.id}`)}
          />
        </CardContent>
      </Card>
    </>
  )
}
