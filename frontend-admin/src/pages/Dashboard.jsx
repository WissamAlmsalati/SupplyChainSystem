import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useModulePermission } from '../hooks/usePermission'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import QuickOrderModal from '../components/QuickOrderModal'
import { Skeleton, SkeletonCard } from '../components/ui/Skeleton'

const statusColors = {
  pending: '#d97706',
  processing: '#0f766e',
  completed: '#16a34a',
  delivered: '#16a34a',
  cancelled: '#dc2626',
  failed: '#dc2626',
  confirmed: '#2563eb',
  shipped: '#9333ea',
}

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
  const num = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''))
  if (!Number.isFinite(num)) return '0.00'
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Dashboard() {
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [quickOpen, setQuickOpen] = useState(false)
  const { canCreate: canCreateOrder } = useModulePermission('ORDERS')

  const load = async () => {
    setLoading(true)
    try {
      const res = await client.get('/dashboard')
      setData(res.data)
    } catch (err) {
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  if (loading) return <DashboardSkeleton />
  if (!data) return <div className="text-danger">فشل تحميل لوحة التحكم.</div>

  const {
    stats = {},
    ordersByStatus = {},
    recentOrders = [],
    topProducts = [],
    monthlyRevenue = [],
    recentLogs = [],
    lowStockItems = [],
  } = data

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

  const maxRevenue = Math.max(
    ...monthlyRevenue.map((m) => {
      const val = Number(typeof m.revenue === 'number' ? m.revenue : String(m.revenue).replace(/,/g, ''))
      return Number.isFinite(val) ? val : 0
    }),
    1,
  )

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">لوحة التحكم</h1>
          <p className="mt-1 text-muted">نظرة عامة وتحليلات على أداء المتجر</p>
        </div>
        {canCreateOrder && (
          <Button variant="secondary" onClick={() => setQuickOpen(true)}>
            + طلب سريع
          </Button>
        )}
      </header>

      <QuickOrderModal open={quickOpen} onClose={() => setQuickOpen(false)} onCreated={load} />

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <StatCard label="إجمالي الطلبات" value={stats.orders} />
        <StatCard label="المنتجات" value={stats.products} />
        <StatCard label="إجمالي الإيرادات" value={`${formatMoney(stats.revenue)} د.ل`} />
        <StatCard
          label="منتجات منخفضة المخزون"
          value={stats.lowStock}
          tone={stats.lowStock > 0 ? 'danger' : 'default'}
        />
        <StatCard label="فروع المقاهي" value={stats.branches} />
      </div>

      {/* Charts */}
      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        {/* Monthly revenue */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>الإيرادات الشهرية (آخر 6 أشهر)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex h-80 items-end justify-between gap-2">
              {monthlyRevenue.map((m) => {
                const revenue = Number(typeof m.revenue === 'number' ? m.revenue : String(m.revenue).replace(/,/g, ''))
                const height = `${Number.isFinite(revenue) && maxRevenue > 0 ? (revenue / maxRevenue) * 100 : 0}%`
                return (
                  <div key={m.month} className="flex h-full flex-1 flex-col items-center gap-2">
                    <div className="text-xs text-muted">{formatMoney(revenue)}</div>
                    <div className="flex flex-1 w-full items-end justify-center">
                      <div
                        className="w-full max-w-[3rem] min-h-1 rounded-t-md bg-primary/80 transition-all hover:bg-primary"
                        style={{ height }}
                        title={`${m.month}: ${formatMoney(revenue)} د.ل`}
                      />
                    </div>
                    <div className="text-xs text-muted">
                      {new Date(`${m.month}-01`).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
                    </div>
                  </div>
                )
              })}
            </div>
          </CardContent>
        </Card>

        {/* Orders by status */}
        <Card>
          <CardHeader>
            <CardTitle>حالات الطلبات</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-4">
              <div
                className="h-28 w-28 rounded-full"
                style={{
                  background: `conic-gradient(${gradient})`,
                }}
              />
              <div className="flex-1 space-y-2">
                {statusEntries.map(([status, count]) => (
                  <div key={status} className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-2">
                      <span
                        className="h-3 w-3 rounded-full"
                        style={{ background: statusColors[status] || '#78716c' }}
                      />
                      <span className="text-foreground">{statusLabels[status] || status}</span>
                    </div>
                    <span className="font-semibold text-muted">{count}</span>
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Lists */}
      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        {/* Top products */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>أكثر المنتجات مبيعاً</CardTitle>
            <button
              onClick={() => navigate('/products')}
              className="text-sm font-medium text-primary hover:underline"
            >
              عرض الكل
            </button>
          </CardHeader>
          <CardContent>
            {topProducts.length === 0 ? (
              <div className="text-sm text-muted">لا توجد بيانات مبيعات.</div>
            ) : (
              <ul className="space-y-1">
                {topProducts.map((p, i) => (
                  <li
                    key={p.id ?? i}
                    onClick={() => p.product_id && navigate(`/products/${p.product_id}`)}
                    className="flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-background/60"
                  >
                    <span className="text-foreground">{p.name}</span>
                    <Badge variant="primary">{p.quantity}</Badge>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        {/* Low stock */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>تنبيهات المخزون</CardTitle>
            <button
              onClick={() => navigate('/inventory')}
              className="text-sm font-medium text-primary hover:underline"
            >
              عرض الكل
            </button>
          </CardHeader>
          <CardContent>
            {lowStockItems.length === 0 ? (
              <div className="text-sm text-muted">لا توجد منتجات منخفضة.</div>
            ) : (
              <ul className="space-y-1">
                {lowStockItems.map((item) => (
                  <li
                    key={item.id}
                    onClick={() =>
                      item.product_variant?.product?.id && navigate(`/products/${item.product_variant.product.id}`)
                    }
                    className="flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-background/60"
                  >
                    <span className="text-foreground">
                      {item.product_variant?.product?.name ?? 'منتج'}
                      {item.product_variant?.attribute_value ? ` - ${item.product_variant.attribute_value}` : ''}
                    </span>
                    <Badge variant="danger">{item.quantity}</Badge>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        {/* Recent orders */}
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
            {recentOrders.length === 0 ? (
              <div className="text-sm text-muted">لا توجد طلبات.</div>
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
                      <div className="text-xs text-muted">{o.user?.name ?? '-'}</div>
                    </div>
                    <div className="flex items-center gap-1">
                      {o.source === 'add order from dashboard' && (
                        <Badge variant="primary">من الـ Dashboard</Badge>
                      )}
                      <Badge variant={o.status === 'completed' ? 'success' : 'warning'}>
                        {statusLabels[o.status] || o.status}
                      </Badge>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Recent activity */}
      <Card className="mt-6">
        <CardHeader>
          <CardTitle>آخر النشاطات</CardTitle>
        </CardHeader>
        <CardContent>
          {recentLogs.length === 0 ? (
            <div className="text-sm text-muted">لا توجد نشاطات.</div>
          ) : (
            <ul className="divide-y divide-border">
              {recentLogs.map((log) => (
                <li key={log.id} className="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="text-sm text-foreground">{log.description}</div>
                  <div className="text-xs text-muted">
                    {log.user_name} — {log.created_at ? new Date(log.created_at).toLocaleString('en-US') : '-'}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </>
  )
}

function StatCard({ label, value, tone = 'default' }) {
  const tones = {
    default: 'border-border bg-surface',
    danger: 'border-danger/20 bg-danger-soft',
    warning: 'border-warning/20 bg-warning-soft',
  }

  return (
    <Card className={tones[tone]}>
      <CardContent className="p-0">
        <div className="text-sm font-medium text-muted">{label}</div>
        <div className="mt-2 text-3xl font-extrabold text-foreground">{value}</div>
      </CardContent>
    </Card>
  )
}

function DashboardSkeleton() {
  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Skeleton className="h-8 w-40" />
          <Skeleton className="mt-2 h-4 w-64" />
        </div>
        <Skeleton className="h-10 w-28" />
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        {Array.from({ length: 5 }).map((_, i) => (
          <SkeletonCard key={i} />
        ))}
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <Skeleton className="h-5 w-48" />
          </CardHeader>
          <CardContent>
            <div className="flex h-80 items-end justify-between gap-2">
              {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="w-full max-w-[3rem] rounded-t-md" style={{ height: `${30 + i * 10}%` }} />
              ))}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <Skeleton className="h-5 w-32" />
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-4">
              <Skeleton circle className="h-28 w-28" />
              <div className="flex-1 space-y-3">
                {Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="flex items-center justify-between">
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-3 w-8" />
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Card key={i}>
            <CardHeader>
              <Skeleton className="h-5 w-40" />
            </CardHeader>
            <CardContent className="space-y-3">
              {Array.from({ length: 4 }).map((__, j) => (
                <div key={j} className="flex items-center justify-between">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-5 w-10 rounded-full" />
                </div>
              ))}
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className="mt-6">
        <CardHeader>
          <Skeleton className="h-5 w-32" />
        </CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex flex-col gap-1 py-1 sm:flex-row sm:items-center sm:justify-between">
              <Skeleton className="h-4 w-48" />
              <Skeleton className="h-3 w-32" />
            </div>
          ))}
        </CardContent>
      </Card>
    </>
  )
}
