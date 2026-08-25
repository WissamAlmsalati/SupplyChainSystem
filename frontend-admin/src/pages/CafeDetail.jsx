import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

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

  if (loading) return <PageSkeleton />
  if (!cafe) return <div className="text-danger">{error || 'المقهى غير موجود.'}</div>

  const branches = cafe.branches ?? []

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المقهى</h1>
          <p className="mt-1 text-muted">{cafe.name || 'مقهى'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/cafes')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات المقهى</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3 text-sm text-foreground">
              {cafe.image_url && (
                <img src={cafe.image_url} alt={cafe.name} className="h-24 w-24 rounded-lg object-cover" />
              )}
              <div><span className="font-medium">الاسم:</span> {cafe.name ?? '-'}</div>
              <div><span className="font-medium">وسيلة التواصل:</span> {cafe.contact_info ?? '-'}</div>
              <div><span className="font-medium">منشئ:</span> {cafe.created_by_admin?.name ?? '-'}</div>
              <div>
                <span className="font-medium">الحالة:</span>{' '}
                <Badge variant={cafe.is_active ? 'success' : 'default'}>{cafe.is_active ? 'نشط' : 'غير نشط'}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ملخص الفروع</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">عدد الفروع:</span> {branches.length}</div>
              <div>
                <span className="font-medium">فروع نشطة:</span>{' '}
                {branches.filter((b) => b.is_active).length}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>فروع المقهى</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={branchColumns}
            rows={branches}
            loading={false}
            emptyText="لا توجد فروع مسجلة لهذا المقهى."
            actions={(row) => (
              <Button variant="secondary" size="sm" onClick={() => navigate(`/cafe-branches/${row.id}`)}>
                عرض الطلبيات
              </Button>
            )}
          />
        </CardContent>
      </Card>
    </>
  )
}
