import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import client from '../api/client'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import { Skeleton, SkeletonCard } from '../components/ui/Skeleton'
import { statusLabels, statusColors, StatusBadge } from '../lib/status'

function formatMoney(value) {
  const num = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''))
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const arabicMonths = [
  'يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
]

export default function MonthlyStats() {
  const { yearMonth } = useParams()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [year, month] = String(yearMonth || '').split('-').map((v) => parseInt(v, 10))
  const valid = year >= 2000 && month >= 1 && month <= 12
  const monthLabel = valid ? `${arabicMonths[month - 1]} ${year}` : yearMonth

  useEffect(() => {
    if (!valid) {
      setLoading(false)
      setError('الشهر المطلوب غير صالح.')
      return
    }
    setLoading(true)
    client.get(`/dashboard/monthly/${year}/${month}`)
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false))
  }, [yearMonth])

  if (loading) return <StatsSkeleton />
  if (error || !data) return <div className="text-danger">{error || 'فشل تحميل إحصائيات الشهر.'}</div>

  const { stats = {}, ordersByStatus = {}, topProducts = [], recentOrders = [] } = data

  const statusEntries = Object.entries(ordersByStatus || {})
  const totalStatus = statusEntries.reduce((sum, [, count]) => sum + count, 0) || 1
  const gradient = statusEntries
    .map(([status, count], i, arr) => {
      const prev = arr.slice(0, i).reduce((s, [, c]) => s + c, 0)
      const start = (prev / totalStatus) * 360
      const end = ((prev + count) / totalStatus) * 360
      return `${statusColors[status] || '#78716c'} ${start}deg ${end}deg`
    })
    .join(', ')

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">إحصائيات {monthLabel}</h1>
          <p className="mt-1 text-muted">تفاصيل أداء المتجر خلال هذا الشهر</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/')}>لوحة التحكم</Button>
      </header>

      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard label="عدد الطلبات" value={stats.orders ?? 0} />
        <StatCard label="إجمالي الإيرادات" value={`${formatMoney(stats.revenue)} د.ل`} />
        <StatCard label="متوسط قيمة الطلب" value={`${formatMoney(stats.avgOrder)} د.ل`} />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>حالات الطلبات</CardTitle>
          </CardHeader>
          <CardContent>
            {statusEntries.length === 0 ? (
              <div className="text-sm text-muted">لا توجد طلبات هذا الشهر.</div>
            ) : (
              <div className="flex items-center gap-4">
                <div className="h-28 w-28 shrink-0 rounded-full" style={{ background: `conic-gradient(${gradient})` }} />
                <div className="flex-1 space-y-2">
                  {statusEntries.map(([status, count]) => (
                    <div key={status} className="flex items-center justify-between text-sm">
                      <div className="flex items-center gap-2">
                        <span className="h-3 w-3 rounded-full" style={{ background: statusColors[status] || '#78716c' }} />
                        <span className="text-foreground">{statusLabels[status] || status}</span>
                      </div>
                      <span className="font-semibold text-muted">{count}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>أكثر المنتجات مبيعاً</CardTitle>
            <button onClick={() => navigate('/products')} className="text-sm font-medium text-primary hover:underline">
              عرض الكل
            </button>
          </CardHeader>
          <CardContent>
            {topProducts.length === 0 ? (
              <div className="text-sm text-muted">لا توجد مبيعات هذا الشهر.</div>
            ) : (
              <ul className="space-y-1">
                {topProducts.map((p, i) => (
                  <li
                    key={p.id ?? i}
                    onClick={() => p.product_id && navigate(`/products/${p.product_id}`)}
                    className="flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-background/60"
                  >
                    <span className="text-foreground">{p.name}</span>
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-muted">{formatMoney(p.revenue)} د.ل</span>
                      <Badge variant="primary">{p.quantity}</Badge>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>طلبات الشهر</CardTitle>
            <button onClick={() => navigate('/orders')} className="text-sm font-medium text-primary hover:underline">
              عرض الكل
            </button>
          </CardHeader>
          <CardContent>
            {recentOrders.length === 0 ? (
              <div className="text-sm text-muted">لا توجد طلبات هذا الشهر.</div>
            ) : (
              <ul className="space-y-1">
                {recentOrders.map((o) => (
                  <li
                    key={o.id}
                    onClick={() => navigate(`/orders/${o.id}`)}
                    className="flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-background/60"
                  >
                    <div>
                      <div className="font-medium text-foreground">{o.order_number ?? `طلب #${o.id}`}</div>
                      <div className="text-xs text-muted">
                        {o.user?.name ?? '-'} · {formatMoney(o.total_amount)} د.ل
                      </div>
                    </div>
                    <StatusBadge status={o.status} />
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  )
}

function StatCard({ label, value }) {
  return (
    <Card>
      <CardContent className="p-0">
        <div className="text-sm font-medium text-muted">{label}</div>
        <div className="mt-2 text-3xl font-extrabold text-foreground">{value}</div>
      </CardContent>
    </Card>
  )
}

function StatsSkeleton() {
  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Skeleton className="h-8 w-48" />
          <Skeleton className="mt-2 h-4 w-64" />
        </div>
        <Skeleton className="h-10 w-28" />
      </header>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <SkeletonCard key={i} />
        ))}
      </div>
      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <SkeletonCard key={i} />
        ))}
      </div>
    </>
  )
}
