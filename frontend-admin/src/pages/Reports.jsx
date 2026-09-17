import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FileDown, FileSpreadsheet, RefreshCw } from 'lucide-react'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { Skeleton } from '../components/ui/Skeleton'
import { downloadFile, downloadError } from '../lib/download'
import { formatMoney } from '../lib/wallet'

const inputClass = 'rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'

const PAYMENT_LABELS = { cash: 'نقداً', card: 'بطاقة', bank_transfer: 'تحويل بنكي', wallet: 'المحفظة' }

function iso(d) {
  return d.toISOString().slice(0, 10)
}

const presets = [
  { label: 'اليوم', from: () => iso(new Date()) },
  { label: 'آخر 7 أيام', from: () => iso(new Date(Date.now() - 6 * 864e5)) },
  { label: 'آخر 30 يوم', from: () => iso(new Date(Date.now() - 29 * 864e5)) },
  { label: 'هذا الشهر', from: () => { const d = new Date(); return iso(new Date(d.getFullYear(), d.getMonth(), 1)) } },
  { label: 'هذه السنة', from: () => iso(new Date(new Date().getFullYear(), 0, 1)) },
]

function Stat({ label, value, tone }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4 shadow-sm">
      <div className="text-xs text-muted">{label}</div>
      <div className={`mt-1 text-2xl font-black ${tone === 'danger' ? 'text-danger' : tone === 'success' ? 'text-success' : 'text-foreground'}`}>{value}</div>
    </div>
  )
}

function SimpleTable({ columns, rows, empty = 'لا توجد بيانات' }) {
  if (!rows?.length) return <div className="text-sm text-muted">{empty}</div>
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead><tr className="border-b border-border text-right text-xs text-muted">{columns.map((c) => <th key={c.key} className={`py-2 ${c.num ? 'text-left' : ''}`}>{c.label}</th>)}</tr></thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i} className="border-b border-border/60 last:border-0">
              {columns.map((c) => <td key={c.key} className={`py-2 ${c.num ? 'text-left tabular-nums' : ''}`} dir={c.num ? 'ltr' : undefined}>{c.render ? c.render(r) : r[c.key]}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default function Reports() {
  const [tab, setTab] = useState('sales')
  const [from, setFrom] = useState(presets[2].from())
  const [to, setTo] = useState(iso(new Date()))
  const [groupBy, setGroupBy] = useState('day')
  const [warehouses, setWarehouses] = useState([])
  const [warehouseId, setWarehouseId] = useState('')
  const [lowStockAt, setLowStockAt] = useState(10)
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [downloading, setDownloading] = useState('')
  const [error, setError] = useState('')

  const params = tab === 'sales'
    ? { from, to, group_by: groupBy }
    : { from, to, warehouse_id: warehouseId || undefined, low_stock_at: lowStockAt }
  const endpoint = tab === 'sales' ? '/reports/sales' : '/reports/inventory'

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(endpoint, { params })
      setData(res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل التقرير')
      setData(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [tab, from, to, groupBy, warehouseId, lowStockAt])
  useEffect(() => {
    client.get('/warehouses', { params: { per_page: 100 } }).then((r) => setWarehouses(r.data?.data ?? [])).catch(() => {})
  }, [])

  const download = async (format, path = endpoint, extra = {}) => {
    setDownloading(`${path}:${format}`)
    setError('')
    try {
      await downloadFile(path, { ...params, ...extra, format })
    } catch (err) {
      setError(await downloadError(err))
    } finally {
      setDownloading('')
    }
  }

  const s = data?.summary ?? {}

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">التقارير</h1>
          <p className="mt-1 text-sm text-muted">مبيعات، مخزون، وتصدير الطلبات. كشوف العهدة والمحافظ من صفحة كل مندوب أو محفظة.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => download('pdf')} disabled={!!downloading || loading}><FileDown className="h-4 w-4" /> PDF</Button>
          <Button variant="secondary" onClick={() => download('xlsx')} disabled={!!downloading || loading}><FileSpreadsheet className="h-4 w-4" /> Excel</Button>
          {tab === 'sales' && (
            <Button variant="primary" onClick={() => download('xlsx', '/reports/orders')} disabled={!!downloading}><FileSpreadsheet className="h-4 w-4" /> تصدير الطلبات</Button>
          )}
        </div>
      </header>

      <div className="mb-4 flex gap-1 rounded-lg border border-border bg-surface p-1 w-fit">
        {[['sales', 'المبيعات'], ['inventory', 'المخزون']].map(([key, label]) => (
          <button key={key} onClick={() => setTab(key)} className={`rounded-md px-4 py-1.5 text-sm font-medium transition ${tab === key ? 'bg-primary text-primary-foreground' : 'text-muted hover:text-foreground'}`}>{label}</button>
        ))}
      </div>

      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-end gap-3 pt-5">
          <label className="text-sm text-muted">من<input type="date" className={`${inputClass} mt-1 block`} value={from} max={to} onChange={(e) => setFrom(e.target.value)} /></label>
          <label className="text-sm text-muted">إلى<input type="date" className={`${inputClass} mt-1 block`} value={to} min={from} onChange={(e) => setTo(e.target.value)} /></label>
          {tab === 'sales' ? (
            <label className="text-sm text-muted">التجميع
              <select className={`${inputClass} mt-1 block`} value={groupBy} onChange={(e) => setGroupBy(e.target.value)}>
                <option value="day">يومي</option><option value="month">شهري</option>
              </select>
            </label>
          ) : (
            <>
              <label className="text-sm text-muted">المستودع
                <select className={`${inputClass} mt-1 block`} value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)}>
                  <option value="">كل المستودعات</option>
                  {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                </select>
              </label>
              <label className="text-sm text-muted">حد النواقص<input type="number" min="0" className={`${inputClass} mt-1 block w-24`} value={lowStockAt} onChange={(e) => setLowStockAt(Number(e.target.value) || 0)} /></label>
            </>
          )}
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
        <div className="grid gap-4 sm:grid-cols-4">{[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-20" />)}</div>
      ) : !data ? null : tab === 'sales' ? (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Stat label="الطلبات" value={s.orders} />
            <Stat label="الإيرادات (د.ل)" value={formatMoney(s.revenue)} />
            <Stat label="متوسط الطلب" value={formatMoney(s.avg_order)} />
            <Stat label="القطع المباعة" value={s.items_sold} />
            <Stat label="المحصّل" value={formatMoney(s.collected)} tone="success" />
            <Stat label="المتبقي" value={formatMoney(s.outstanding)} tone="danger" />
            <Stat label="رسوم التوصيل" value={formatMoney(s.delivery_fees)} />
            <Stat label="طلبات ملغية" value={s.cancelled} tone="danger" />
          </div>
          <div className="grid gap-6 lg:grid-cols-2">
            <Card><CardHeader><CardTitle>{groupBy === 'month' ? 'حسب الشهر' : 'حسب اليوم'}</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'bucket', label: 'الفترة', num: true }, { key: 'orders', label: 'الطلبات', num: true }, { key: 'revenue', label: 'الإيرادات', num: true, render: (r) => formatMoney(r.revenue) }]} rows={data.series} empty="لا توجد طلبات في الفترة" />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>حالات الطلبات</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'label', label: 'الحالة' }, { key: 'orders', label: 'الطلبات', num: true }, { key: 'amount', label: 'المبلغ', num: true, render: (r) => formatMoney(r.amount) }]} rows={data.by_status} />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>أعلى المنتجات مبيعاً</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'product', label: 'المنتج', render: (r) => <>{r.product} <span className="text-muted">{r.variant}</span></> }, { key: 'quantity', label: 'الكمية', num: true }, { key: 'revenue', label: 'الإيرادات', num: true, render: (r) => formatMoney(r.revenue) }]} rows={data.top_products} />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>أعلى الزبائن</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'name', label: 'الزبون', render: (r) => <Link className="hover:text-primary" to={`/users/${r.id}`}>{r.name}</Link> }, { key: 'orders', label: 'الطلبات', num: true }, { key: 'revenue', label: 'الإيرادات', num: true, render: (r) => formatMoney(r.revenue) }]} rows={data.top_customers} />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>المناديب</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'name', label: 'المندوب', render: (r) => <Link className="hover:text-primary" to={`/delegates/${r.id}`}>{r.name}</Link> }, { key: 'orders', label: 'الطلبات', num: true }, { key: 'revenue', label: 'الإيرادات', num: true, render: (r) => formatMoney(r.revenue) }]} rows={data.by_delegate} />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>طرق الدفع</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'method', label: 'الطريقة', render: (r) => PAYMENT_LABELS[r.method] ?? r.method }, { key: 'count', label: 'العدد', num: true }, { key: 'amount', label: 'المبلغ', num: true, render: (r) => formatMoney(r.amount) }]} rows={data.payments} />
            </CardContent></Card>
          </div>
        </>
      ) : (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Stat label="أصناف" value={s.lines} />
            <Stat label="وحدات في المخزون" value={s.units} />
            <Stat label="قيمة المخزون (بسعر البيع)" value={formatMoney(s.value)} />
            <Stat label={`نواقص (≤ ${data.low_stock_at})`} value={`${s.low_stock} · صفر: ${s.out_of_stock}`} tone="danger" />
          </div>
          <div className="grid gap-6 lg:grid-cols-2">
            <Card><CardHeader><CardTitle>حركات الفترة</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'label', label: 'النوع' }, { key: 'count', label: 'العدد', num: true }, { key: 'in', label: 'داخل', num: true }, { key: 'out', label: 'خارج', num: true }]} rows={data.movements} empty="لا توجد حركات في الفترة" />
            </CardContent></Card>
            <Card><CardHeader><CardTitle>نواقص</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'warehouse', label: 'المستودع' }, { key: 'product', label: 'المنتج', render: (r) => <>{r.product} <span className="text-muted">{r.variant}</span></> }, { key: 'quantity', label: 'الكمية', num: true }]} rows={data.low_stock} empty="لا توجد نواقص" />
            </CardContent></Card>
            <Card className="lg:col-span-2"><CardHeader><CardTitle>الأرصدة</CardTitle></CardHeader><CardContent>
              <SimpleTable columns={[{ key: 'warehouse', label: 'المستودع' }, { key: 'product', label: 'المنتج', render: (r) => <>{r.product} <span className="text-muted">{r.variant}</span></> }, { key: 'sku', label: 'SKU', num: true }, { key: 'quantity', label: 'الكمية', num: true }, { key: 'price', label: 'السعر', num: true, render: (r) => formatMoney(r.price) }, { key: 'value', label: 'القيمة', num: true, render: (r) => formatMoney(r.value) }]} rows={data.levels} />
            </CardContent></Card>
          </div>
        </>
      )}
    </>
  )
}
