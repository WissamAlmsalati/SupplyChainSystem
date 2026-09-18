import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { FileDown, FileSpreadsheet, RefreshCw, Timer, Trophy, HandCoins, TriangleAlert } from 'lucide-react'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { Skeleton } from '../components/ui/Skeleton'
import { downloadFile, downloadError } from '../lib/download'
import { formatMoney } from '../lib/wallet'

const inputClass = 'rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'
const iso = (d) => d.toISOString().slice(0, 10)
const presets = [
  { label: 'اليوم', from: () => iso(new Date()) },
  { label: 'آخر 7 أيام', from: () => iso(new Date(Date.now() - 6 * 864e5)) },
  { label: 'آخر 30 يوم', from: () => iso(new Date(Date.now() - 29 * 864e5)) },
  { label: 'هذا الشهر', from: () => { const d = new Date(); return iso(new Date(d.getFullYear(), d.getMonth(), 1)) } },
]

// Cash held longer than this without a settlement is worth a phone call.
const CUSTODY_DAYS_ALERT = 3

const minutes = (v) => {
  if (v === null || v === undefined) return '—'
  if (v < 60) return `${v} د`
  return `${Math.floor(v / 60)} س ${v % 60} د`
}
const pct = (v) => (v === null || v === undefined ? '—' : `${v}%`)

function Stat({ icon: Icon, label, value, hint, tone }) {
  const tones = { success: 'bg-success-soft text-success', warning: 'bg-warning-soft text-warning', default: 'bg-primary/10 text-primary' }
  return (
    <div className="flex items-center gap-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
      <div className={`rounded-lg p-3 ${tones[tone] ?? tones.default}`}><Icon className="h-5 w-5" /></div>
      <div className="min-w-0">
        <div className="text-xs text-muted">{label}</div>
        <div className="text-xl font-black text-foreground">{value}</div>
        {hint && <div className="text-xs text-muted">{hint}</div>}
      </div>
    </div>
  )
}

// A bar per delegate so the ranking reads at a glance, before any number does.
function Ranking({ rows, onOpen }) {
  const top = Math.max(1, ...rows.map((r) => r.delivered))
  if (!rows.length) return <div className="text-sm text-muted">لا توجد توصيلات في الفترة.</div>
  return (
    <ul className="space-y-3">
      {rows.map((r, i) => (
        <li key={r.id}>
          <button onClick={() => onOpen(r)} className="w-full text-right">
            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
              <span className="min-w-0 truncate font-medium text-foreground"><span className="ml-2 text-muted">{i + 1}</span>{r.name}</span>
              <span className="shrink-0 tabular-nums text-muted">{r.delivered} توصيلة · {pct(r.success_rate)}</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-background">
              <div className="h-full rounded-full bg-primary" style={{ width: `${(r.delivered / top) * 100}%` }} />
            </div>
          </button>
        </li>
      ))}
    </ul>
  )
}

export default function DelegatePerformance() {
  const navigate = useNavigate()
  const [from, setFrom] = useState(presets[2].from())
  const [to, setTo] = useState(iso(new Date()))
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [downloading, setDownloading] = useState('')
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get('/reports/delegates', { params: { from, to } })
      setData(res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل أداء المناديب')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => { load() }, [from, to])

  const download = async (format) => {
    setDownloading(format)
    setError('')
    try {
      await downloadFile('/reports/delegates', { from, to, format })
    } catch (err) {
      setError(await downloadError(err))
    } finally {
      setDownloading('')
    }
  }

  const rows = data?.delegates ?? []
  const s = data?.summary ?? {}
  const ranked = useMemo(() => rows.filter((r) => r.delivered > 0).slice(0, 8), [rows])
  const holding = useMemo(
    () => rows.filter((r) => r.custody_balance > 0).sort((a, b) => (b.days_since_settlement ?? -1) - (a.days_since_settlement ?? -1) || b.custody_balance - a.custody_balance),
    [rows],
  )

  const columns = [
    { key: 'name', label: 'المندوب', mobile: 'title', render: (r) => <span className="font-medium">{r.name}{!r.is_active && <span className="mr-2 text-xs text-muted">(موقوف)</span>}</span> },
    { key: 'is_available', label: 'الآن', mobile: 'subtitle', render: (r) => <Badge variant={r.is_available ? 'success' : 'default'}>{r.is_available ? 'متاح' : 'غير متاح'}</Badge> },
    { key: 'assigned', label: 'مسندة' },
    { key: 'delivered', label: 'مسلّمة', render: (r) => <span className="font-bold text-success">{r.delivered}</span> },
    { key: 'cancelled', label: 'ملغاة', render: (r) => <span className={r.cancelled > 0 ? 'text-danger' : 'text-muted'}>{r.cancelled}</span> },
    { key: 'in_progress', label: 'قيد التنفيذ' },
    { key: 'success_rate', label: 'النجاح', render: (r) => pct(r.success_rate) },
    { key: 'avg_delivery_minutes', label: 'زمن التوصيل', render: (r) => minutes(r.avg_delivery_minutes) },
    { key: 'avg_total_minutes', label: 'من الطلب للتسليم', render: (r) => minutes(r.avg_total_minutes) },
    { key: 'cash_collected', label: 'نقد محصّل', render: (r) => `${formatMoney(r.cash_collected)} د.ل` },
    { key: 'custody_balance', label: 'العهدة الآن', render: (r) => <span className={r.custody_balance > 0 ? 'font-bold text-warning' : 'text-muted'}>{formatMoney(r.custody_balance)} د.ل</span> },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">أداء المناديب</h1>
          <p className="mt-1 text-sm text-muted">الأزمنة من سجل حالات الطلب. العهدة وأيامها هي وضع اليوم، لا وضع الفترة.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => download('pdf')} disabled={!!downloading || loading}><FileDown className="h-4 w-4" /> PDF</Button>
          <Button variant="secondary" onClick={() => download('xlsx')} disabled={!!downloading || loading}><FileSpreadsheet className="h-4 w-4" /> Excel</Button>
        </div>
      </header>

      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-end gap-3 pt-5">
          <label className="text-sm text-muted">من<input type="date" className={`${inputClass} mt-1 block`} value={from} max={to} onChange={(e) => setFrom(e.target.value)} /></label>
          <label className="text-sm text-muted">إلى<input type="date" className={`${inputClass} mt-1 block`} value={to} min={from} onChange={(e) => setTo(e.target.value)} /></label>
          <div className="flex flex-wrap gap-1">
            {presets.map((p) => (
              <button key={p.label} onClick={() => { setFrom(p.from()); setTo(iso(new Date())) }} className="rounded-full border border-border px-3 py-1 text-xs text-muted hover:border-primary hover:text-primary">{p.label}</button>
            ))}
          </div>
          <Button variant="ghost" size="sm" onClick={load} disabled={loading}><RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} /></Button>
        </CardContent>
      </Card>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      {loading && !data ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-20" />)}</div>
      ) : !data ? null : (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Stat icon={Trophy} label="مسلّمة من مسندة" value={`${s.delivered} / ${s.assigned}`} hint={`${s.active_in_period} مندوب اشتغل من ${s.delegates}`} tone="success" />
            <Stat icon={Trophy} label="نسبة النجاح" value={pct(s.success_rate)} hint={`${s.cancelled} ملغاة`} />
            <Stat icon={Timer} label="متوسط زمن التوصيل" value={minutes(s.avg_delivery_minutes)} hint="من خروج المندوب إلى التسليم" />
            <Stat icon={HandCoins} label="عهدة عند المناديب الآن" value={`${formatMoney(s.custody_held)} د.ل`} hint={`محصّل في الفترة ${formatMoney(s.cash_collected)}`} tone="warning" />
          </div>

          <div className="mb-6 grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>ترتيب التوصيل</CardTitle></CardHeader>
              <CardContent><Ranking rows={ranked} onOpen={(r) => navigate(`/delegates/${r.id}`)} /></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>نقد لم يُسلَّم للمكتب</CardTitle></CardHeader>
              <CardContent>
                {!holding.length ? <div className="text-sm text-muted">لا يحمل أي مندوب عهدة الآن.</div> : (
                  <ul className="divide-y divide-border">
                    {holding.map((r) => {
                      const late = r.days_since_settlement !== null && r.days_since_settlement >= CUSTODY_DAYS_ALERT
                      return (
                        <li key={r.id} className="flex items-center justify-between gap-3 py-2.5">
                          <div className="min-w-0">
                            <div className="truncate text-sm font-medium text-foreground">{r.name}</div>
                            <div className={`flex items-center gap-1 text-xs ${late ? 'text-danger' : 'text-muted'}`}>
                              {late && <TriangleAlert className="h-3.5 w-3.5" />}
                              {r.days_since_settlement === null ? 'لم يسكّر من قبل' : r.days_since_settlement === 0 ? 'سكّر اليوم' : `${r.days_since_settlement} يوم منذ آخر تسكير`}
                            </div>
                          </div>
                          <div className="flex shrink-0 items-center gap-3">
                            <span className="font-bold tabular-nums text-warning">{formatMoney(r.custody_balance)} د.ل</span>
                            <Button variant="secondary" size="sm" onClick={() => navigate(`/custody/${r.id}`)}>تسكير</Button>
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>

          <DataTable columns={columns} rows={rows} loading={false} emptyText="لا يوجد مناديب." onRowClick={(r) => navigate(`/delegates/${r.id}`)} />
        </>
      )}
    </>
  )
}
