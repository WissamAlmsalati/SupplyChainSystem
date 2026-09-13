import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { HandCoins } from 'lucide-react'
import { useApiResource } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import { Card, CardContent } from '../components/ui/Card'
import { formatMoney, formatDateTime } from '../lib/wallet'

// Cash each delegate holds for the office (عهدة المناديب).
export default function Custody() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [withBalance, setWithBalance] = useState(false)
  const { items, loading, error, pagination, setPage } = useApiResource('/custody', { search, with_balance: withBalance ? 1 : '' })
  const pageTotal = items.reduce((s, d) => s + Number(d.custody_balance || 0), 0)

  const columns = [
    { key: 'name', label: 'المندوب', render: (r) => <span className="font-medium">{r.name}</span> },
    { key: 'mobile_number', label: 'الجوال', render: (r) => <bdi>{r.mobile_number ?? '-'}</bdi> },
    {
      key: 'custody_balance',
      label: 'العهدة الحالية',
      render: (r) => (
        <span className={`font-bold ${Number(r.custody_balance) > 0 ? 'text-warning' : 'text-muted'}`}>{formatMoney(r.custody_balance)} د.ل</span>
      ),
    },
    { key: 'last_settlement_at', label: 'آخر تسكير', render: (r) => (r.last_settlement_at ? formatDateTime(r.last_settlement_at) : <span className="text-muted">لم يتم</span>) },
    { key: 'is_active', label: 'الحالة', render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge> },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">عهد المناديب</h1>
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder="بحث بالاسم أو الجوال..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input type="checkbox" checked={withBalance} onChange={(e) => setWithBalance(e.target.checked)} />
            لديهم عهدة فقط
          </label>
        </div>
      </header>

      <Card className="my-6">
        <CardContent className="flex items-center gap-4 pt-6">
          <div className="rounded-lg bg-warning-soft p-3 text-warning"><HandCoins className="h-6 w-6" /></div>
          <div>
            <div className="text-sm text-muted">نقدية لدى المناديب لم تُسلَّم بعد</div>
            <div className="text-2xl font-extrabold text-foreground">{formatMoney(pagination?.summary?.total_custody ?? pageTotal)} د.ل</div>
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
        emptyText="لا يوجد مناديب."
        actions={(row) => (
          <Button variant={Number(row.custody_balance) > 0 ? 'primary' : 'secondary'} size="sm" onClick={() => navigate(`/custody/${row.id}`)}>
            {Number(row.custody_balance) > 0 ? 'تسكير' : 'عرض'}
          </Button>
        )}
      />
    </>
  )
}
