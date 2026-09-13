import { useEffect, useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useApiResource } from '../hooks/useApiResource'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { FilterSelect } from '../components/ui/TableFilters'
import { formatMoney, formatDateTime, topupMethods } from '../lib/wallet'

const PERIODS = [
  ['today', 'اليوم'],
  ['week', 'هذا الأسبوع'],
  ['month', 'هذا الشهر'],
  ['all', 'كل الفترات'],
]

function StatTile({ label, value, hint, to }) {
  const body = (
    <Card className={`h-full ${to ? 'transition-colors hover:border-primary' : ''}`}>
      <CardContent className="pt-5">
        <div className="text-sm text-muted">{label}</div>
        <div className="mt-1 text-2xl font-semibold text-foreground">{value}</div>
        {hint && <div className="mt-1 text-xs text-muted">{hint}</div>}
      </CardContent>
    </Card>
  )
  return to ? <Link to={to} className="block">{body}</Link> : body
}

function FlowRow({ label, amount, sign, detail }) {
  return (
    <tr>
      <td className="py-2.5 text-foreground">
        {label}
        {detail && <div className="text-xs text-muted">{detail}</div>}
      </td>
      <td className="py-2.5 text-end tabular-nums text-muted">{sign}</td>
      <td className="py-2.5 text-end font-semibold tabular-nums text-foreground">{formatMoney(amount)} د.ل</td>
    </tr>
  )
}

export default function Wallets() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [hasBalance, setHasBalance] = useState('')
  const [period, setPeriod] = useState('month')
  const [summary, setSummary] = useState(null)
  const { items, loading, error, pagination, setPage } = useApiResource('/wallets', { search, has_balance: hasBalance })

  useEffect(() => {
    client.get('/wallets/summary', { params: { period } }).then((res) => setSummary(res.data)).catch(() => setSummary(null))
  }, [period])

  const flows = summary?.flows
  const columns = [
    { key: 'user', label: 'الزبون', render: (r) => <span className="font-medium">{r.user?.name ?? '-'}</span> },
    { key: 'mobile', label: 'الجوال', render: (r) => <bdi>{r.user?.mobile_number ?? '-'}</bdi> },
    { key: 'balance', label: 'الرصيد', render: (r) => <span className="font-semibold tabular-nums text-foreground">{formatMoney(r.balance)} د.ل</span> },
    { key: 'pending_topups_count', label: 'طلبات معلقة', render: (r) => (r.pending_topups_count > 0 ? <Badge variant="warning">{r.pending_topups_count}</Badge> : '-') },
    { key: 'is_active', label: 'الحالة', render: (r) => <Badge variant={r.is_active ? 'success' : 'danger'}>{r.is_active ? 'فعالة' : 'موقوفة'}</Badge> },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">المحافظ والسيولة</h1>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => navigate('/custody')}>عهد المناديب</Button>
          <Button variant="primary" onClick={() => navigate('/wallet-topups')}>طلبات الشحن</Button>
        </div>
      </header>

      {summary && (
        <section className="my-6 space-y-6">
          {/* Headline: all money currently in the system outside the office */}
          <Card>
            <CardContent className="flex flex-col gap-6 pt-6 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div className="text-sm text-muted">إجمالي السيولة خارج المكتب</div>
                <div className="mt-1 text-5xl font-semibold text-foreground">
                  {formatMoney(summary.liquidity.total)} <span className="text-xl font-medium text-muted">د.ل</span>
                </div>
                <div className="mt-2 text-sm text-muted">
                  أرصدة المقاهي في المحافظ + النقدية التي لم يسلّمها المناديب بعد
                </div>
              </div>
              <div className="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:min-w-[440px]">
                <div className="rounded-lg border border-border p-4">
                  <div className="text-sm text-muted">في محافظ المقاهي</div>
                  <div className="mt-1 text-2xl font-semibold text-foreground">{formatMoney(summary.liquidity.customer_wallets)} د.ل</div>
                  <div className="mt-1 text-xs text-muted">مستحق للمقاهي · {summary.wallets.with_balance} من {summary.wallets.count} محفظة بها رصيد</div>
                </div>
                <Link to="/custody" className="block rounded-lg border border-border p-4 transition-colors hover:border-primary">
                  <div className="text-sm text-muted">نقدية لدى المناديب (عهد)</div>
                  <div className="mt-1 text-2xl font-semibold text-foreground">{formatMoney(summary.liquidity.delegate_custody)} د.ل</div>
                  <div className="mt-1 text-xs text-muted">مستحق للمكتب · {summary.delegates_with_custody} مندوب</div>
                </Link>
              </div>
            </CardContent>
          </Card>

          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-lg font-bold text-foreground">حركة الأموال</h2>
            <div className="inline-flex rounded-lg border border-border bg-surface p-1">
              {PERIODS.map(([key, label]) => (
                <button
                  key={key}
                  onClick={() => setPeriod(key)}
                  className={`rounded-md px-3 py-1.5 text-sm ${period === key ? 'bg-primary font-semibold text-primary-foreground' : 'text-foreground hover:bg-background'}`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile label="شحن معتمد للمحافظ" value={`${formatMoney(flows.topups_total)} د.ل`} hint="بكل الطرق خلال الفترة" />
            <StatTile label="مدفوعات الطلبات من المحافظ" value={`${formatMoney(flows.wallet_payments)} د.ل`} hint={`استرجاع: ${formatMoney(flows.wallet_refunds)} د.ل`} />
            <StatTile label="نقدية حصّلها المناديب" value={`${formatMoney(flows.order_cash_collected + flows.wallet_cash_collected)} د.ل`} hint={`تسليمات للمكتب: ${formatMoney(flows.settlements_received)} د.ل`} to="/custody" />
            <StatTile
              label="طلبات شحن بانتظار المراجعة"
              value={`${formatMoney(summary.pending_topups.amount)} د.ل`}
              hint={`${summary.pending_topups.count} طلب`}
              to="/wallet-topups?status=pending"
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-1">
              <CardHeader><CardTitle>تفصيل الحركة</CardTitle></CardHeader>
              <CardContent>
                <table className="w-full text-sm">
                  <tbody className="divide-y divide-border">
                    <tr><td colSpan={3} className="pb-1 pt-0 text-xs font-bold text-muted">المحافظ</td></tr>
                    {Object.entries(flows.topups_by_method).map(([method, amount]) => (
                      <FlowRow key={method} label={`شحن: ${topupMethods[method] ?? method}`} amount={amount} sign="+" />
                    ))}
                    <FlowRow label="استرجاع طلبات ملغاة" amount={flows.wallet_refunds} sign="+" />
                    <FlowRow label="إضافات إدارية" amount={flows.adjustments_in} sign="+" />
                    <FlowRow label="دفع طلبات" amount={flows.wallet_payments} sign="−" />
                    <FlowRow label="خصومات إدارية" amount={flows.adjustments_out} sign="−" />
                    <tr><td colSpan={3} className="pb-1 pt-4 text-xs font-bold text-muted">عهد المناديب</td></tr>
                    <FlowRow label="تحصيل الطلبات نقداً" amount={flows.order_cash_collected} sign="+" />
                    <FlowRow label="تحصيل شحن المحافظ" amount={flows.wallet_cash_collected} sign="+" />
                    <FlowRow label="تسليمات استلمها المكتب" amount={flows.settlements_received} sign="−" />
                  </tbody>
                </table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>النقدية لدى المناديب</CardTitle>
                <Link to="/custody" className="text-xs text-primary hover:underline">الكل</Link>
              </CardHeader>
              <CardContent>
                {summary.delegates.length === 0 ? <div className="text-sm text-muted">لا توجد عهد مفتوحة.</div> : (
                  <ul className="divide-y divide-border text-sm">
                    {summary.delegates.map((d) => (
                      <li key={d.id}>
                        <Link to={`/custody/${d.id}`} className="flex items-center justify-between py-2.5 hover:text-primary">
                          <span>
                            <span className="font-medium">{d.name}</span>
                            <span className="block text-xs text-muted">آخر تسكير: {d.last_settlement_at ? formatDateTime(d.last_settlement_at) : 'لم يتم'}</span>
                          </span>
                          <span className="font-semibold tabular-nums">{formatMoney(d.custody_balance)} د.ل</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle>أعلى أرصدة المقاهي</CardTitle></CardHeader>
              <CardContent>
                {summary.top_wallets.length === 0 ? <div className="text-sm text-muted">لا توجد أرصدة.</div> : (
                  <ul className="divide-y divide-border text-sm">
                    {summary.top_wallets.map((w) => (
                      <li key={w.id}>
                        <Link to={`/wallets/${w.id}`} className="flex items-center justify-between py-2.5 hover:text-primary">
                          <span className="font-medium">{w.user?.name}</span>
                          <span className="font-semibold tabular-nums">{formatMoney(w.balance)} د.ل</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>
        </section>
      )}

      <div className="mb-4 mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-bold text-foreground">محافظ المقاهي</h2>
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder="بحث بالاسم أو الجوال..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <FilterSelect label="الرصيد" value={hasBalance} onChange={setHasBalance} options={[{ value: '1', label: 'لديه رصيد' }, { value: '0', label: 'رصيد صفر' }]} />
        </div>
      </div>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد محافظ."
        actions={(row) => <Button variant="secondary" size="sm" onClick={() => navigate(`/wallets/${row.id}`)}>التفاصيل</Button>}
      />
    </>
  )
}
