import { useEffect, useState } from 'react'
import client from '../api/client'

const statusLabels = {
  pending: 'معلّق',
  processing: 'قيد المعالجة',
  completed: 'مكتمل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
  failed: 'فاشل',
}

const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
}

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function Card({ children, className = '' }) {
  return <div className={`rounded-xl border border-border bg-surface p-5 shadow-sm ${className}`}>{children}</div>
}

export default function Dashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    client
      .get('/cafe/dashboard')
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="pt-6 text-muted">جاري التحميل...</div>
  if (!data) return <div className="pt-6 text-danger">فشل تحميل لوحة التحكم.</div>

  const { cafe, stats, ordersByStatus, recentOrders, monthlyRevenue } = data
  const statusEntries = Object.entries(ordersByStatus || {})
  const totalStatus = statusEntries.reduce((sum, [, count]) => sum + count, 0) || 1
  const maxRevenue = Math.max(...monthlyRevenue.map((m) => Number(m.revenue) || 0), 1)

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">لوحة تحكم {cafe?.name}</h1>
        <p className="mt-1 text-muted">نظرة سريعة على أداء مقهاك</p>
      </header>

      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <div className="text-sm text-muted">إجمالي الطلبات</div>
          <div className="mt-2 text-3xl font-extrabold text-foreground">{stats.orders}</div>
        </Card>
        <Card>
          <div className="text-sm text-muted">عدد الفروع</div>
          <div className="mt-2 text-3xl font-extrabold text-foreground">{stats.branches}</div>
        </Card>
        <Card>
          <div className="text-sm text-muted">إجمالي الإيرادات</div>
          <div className="mt-2 text-3xl font-extrabold text-foreground">{formatMoney(stats.revenue)} د.ل</div>
        </Card>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <div className="mb-4 font-semibold text-foreground">الإيرادات الشهرية (آخر 6 أشهر)</div>
          <div className="flex h-48 items-end justify-between gap-2">
            {monthlyRevenue.map((m) => {
              const height = `${(Number(m.revenue) / maxRevenue) * 100}%`
              return (
                <div key={m.month} className="flex flex-1 flex-col items-center gap-2">
                  <div
                    className="w-full max-w-[2.5rem] rounded-t-md bg-primary/80"
                    style={{ height }}
                    title={`${formatMoney(m.revenue)} د.ل`}
                  />
                  <div className="text-xs text-muted">
                    {new Date(`${m.month}-01`).toLocaleDateString('en-US', { month: 'short' })}
                  </div>
                </div>
              )
            })}
          </div>
        </Card>

        <Card>
          <div className="mb-4 font-semibold text-foreground">حالات الطلبات</div>
          <div className="space-y-3">
            {statusEntries.map(([status, count]) => (
              <div key={status} className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                  <span className="h-3 w-3 rounded-full" style={{ background: statusColors[status] || '#78716c' }} />
                  <span className="text-foreground">{statusLabels[status] || status}</span>
                </div>
                <span className="font-semibold text-muted">{count}</span>
              </div>
            ))}
            {statusEntries.length === 0 && <div className="text-sm text-muted">لا توجد طلبات.</div>}
          </div>
        </Card>
      </div>

      <Card className="mt-6">
        <div className="mb-4 font-semibold text-foreground">آخر الطلبات</div>
        <div className="divide-y divide-border">
          {recentOrders.length === 0 && <div className="py-4 text-sm text-muted">لا توجد طلبات.</div>}
          {recentOrders.map((o) => (
            <div key={o.id} className="flex items-center justify-between py-3 text-sm">
              <div>
                <div className="font-medium text-foreground">طلب #{o.id}</div>
                <div className="text-xs text-muted">{o.user?.name ?? '-'}</div>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-muted">{formatMoney(o.total_amount)} د.ل</span>
                <span
                  className="rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                  style={{ background: statusColors[o.status] || '#78716c' }}
                >
                  {statusLabels[o.status] || o.status}
                </span>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </>
  )
}
