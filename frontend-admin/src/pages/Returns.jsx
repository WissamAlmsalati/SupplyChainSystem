import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Undo2 } from 'lucide-react'
import { useApiResource } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { Card, CardContent } from '../components/ui/Card'
import { formatMoney, formatDateTime } from '../lib/wallet'

const REFUND_METHODS = { wallet: 'إلى المحفظة', cash: 'نقداً', none: 'بدون استرداد' }

// Goods that came back after delivery. A return is recorded from its order's
// page, where the delivered quantities are; this page is the register.
export default function Returns() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { items, loading, error, pagination, setPage } = useApiResource('/returns', { search })
  const summary = pagination?.summary ?? {}

  const columns = [
    { key: 'order', label: 'الطلب', mobile: 'title', render: (r) => <bdi className="font-medium">{r.order?.order_number ?? `#${r.order_id}`}</bdi> },
    { key: 'customer', label: 'الزبون', mobile: 'subtitle', render: (r) => r.order?.user?.name ?? '-' },
    { key: 'reason', label: 'السبب' },
    { key: 'items_quantity', label: 'الوحدات', render: (r) => r.items_quantity ?? '-' },
    { key: 'total_value', label: 'القيمة', render: (r) => <span className="font-semibold text-danger">{formatMoney(r.total_value)} د.ل</span> },
    {
      key: 'refund_amount',
      label: 'الاسترداد',
      render: (r) => (Number(r.refund_amount) > 0
        ? <span>{formatMoney(r.refund_amount)} د.ل <Badge variant={r.refund_method === 'wallet' ? 'success' : 'warning'}>{REFUND_METHODS[r.refund_method]}</Badge></span>
        : <span className="text-muted">{REFUND_METHODS.none}</span>),
    },
    { key: 'created_by', label: 'سجّله', mobile: 'hide', render: (r) => r.created_by?.name ?? '-' },
    { key: 'created_at', label: 'التاريخ', render: (r) => formatDateTime(r.created_at) },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">المرتجعات</h1>
          <p className="mt-1 text-sm text-muted">يُسجَّل المرتجع من صفحة الطلب بعد تسليمه.</p>
        </div>
        <input
          type="text"
          placeholder="بحث برقم الطلب أو اسم الزبون أو جواله..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-80"
        />
      </header>

      <Card className="my-6">
        <CardContent className="flex flex-wrap items-center gap-x-10 gap-y-4 pt-6">
          <div className="flex items-center gap-4">
            <div className="rounded-lg bg-danger-soft p-3 text-danger"><Undo2 className="h-6 w-6" /></div>
            <div>
              <div className="text-sm text-muted">قيمة المرتجعات{search ? ' في نتيجة البحث' : ''}</div>
              <div className="text-2xl font-extrabold text-foreground">{formatMoney(summary.total_value ?? 0)} د.ل</div>
            </div>
          </div>
          <div>
            <div className="text-sm text-muted">استُرد للزبائن</div>
            <div className="text-2xl font-extrabold text-foreground">{formatMoney(summary.refunded ?? 0)} د.ل</div>
          </div>
        </CardContent>
      </Card>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد مرتجعات."
        onRowClick={(row) => navigate(`/orders/${row.order_id}`)}
      />
    </>
  )
}
