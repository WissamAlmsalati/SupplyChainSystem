import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowRight, HandCoins, Printer, FileDown } from 'lucide-react'
import client from '../api/client'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import Modal from '../components/Modal'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { PageSkeleton } from '../components/ui/Skeleton'
import { useModulePermission } from '../hooks/usePermission'
import { custodyEntryTypes, formatMoney, formatDateTime } from '../lib/wallet'
import { downloadFile, downloadError } from '../lib/download'

const inputClass = 'w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'

function referenceLink(entry) {
  if (entry.reference_type?.endsWith('Order')) return `/orders/${entry.reference_id}`
  if (entry.reference_type?.endsWith('WalletTopup')) return `/wallet-topups/${entry.reference_id}`
  return null
}

export default function CustodyDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { canEdit } = useModulePermission('CUSTODY')
  const [data, setData] = useState(null)
  const [entries, setEntries] = useState({ data: [], current_page: 1, last_page: 1 })
  const normalizePage = (payload) => ({ data: payload?.data ?? [], ...(payload?.meta ?? payload ?? {}) })
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [settle, setSettle] = useState(null)
  const [receipt, setReceipt] = useState(null)
  const [saving, setSaving] = useState(false)

  const load = async () => {
    const [summary, list] = await Promise.all([
      client.get(`/custody/${id}`),
      client.get(`/custody/${id}/entries`, { params: { page } }),
    ])
    setData(summary.data)
    setEntries(normalizePage(list.data))
  }

  useEffect(() => {
    load().catch((err) => setError(err.response?.data?.message || 'فشل تحميل العهدة'))
  }, [id, page])

  const submitSettle = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const res = await client.post(`/custody/${id}/settle`, { amount: Number(settle.amount), note: settle.note || null })
      setSettle(null)
      setReceipt(res.data?.data)
      setPage(1)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تسجيل التسكير')
    } finally {
      setSaving(false)
    }
  }

  if (!data) return error ? <div className="text-danger">{error}</div> : <PageSkeleton />

  const balance = Number(data.balance)

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <button onClick={() => navigate('/custody')} className="mt-1 rounded-md border border-border p-2 text-muted hover:bg-background" aria-label="رجوع">
            <ArrowRight className="h-4 w-4" />
          </button>
          <div>
            <h1 className="text-2xl font-extrabold text-foreground">عهدة {data.delegate.name}</h1>
            <p className="mt-1 text-sm text-muted">
              <Link to={`/delegates/${data.delegate.id}`} className="hover:text-primary"><bdi>{data.delegate.mobile_number}</bdi></Link>
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => downloadFile(`/reports/custody/${id}`, { all: 1, format: 'pdf' }).catch(async (e) => setError(await downloadError(e)))}>
            <FileDown className="h-4 w-4" /> كشف العهدة PDF
          </Button>
          {canEdit && balance > 0 && (
            <Button variant="primary" onClick={() => setSettle({ amount: balance.toFixed(2), note: '' })}>
              <HandCoins className="h-4 w-4" /> تسكير الحساب
            </Button>
          )}
        </div>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger print:hidden">{error}</div>}

      <div className="mb-6 grid gap-4 print:hidden sm:grid-cols-3">
        <div className={`rounded-xl p-5 shadow-sm ${balance > 0 ? 'bg-amber-500 text-white' : 'bg-primary text-primary-foreground'}`}>
          <div className="text-sm opacity-90">العهدة الحالية</div>
          <div className="mt-1 text-3xl font-black">{formatMoney(balance)} <span className="text-base">د.ل</span></div>
        </div>
        <Card>
          <CardContent className="pt-5">
            <div className="text-sm text-muted">منذ آخر تسكير</div>
            <div className="mt-1 text-2xl font-bold text-foreground">{formatMoney(data.since_last_settlement.amount)} د.ل</div>
            <div className="text-xs text-muted">{data.since_last_settlement.collections} عملية تحصيل</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-5">
            <div className="text-sm text-muted">آخر تسكير</div>
            {data.last_settlement ? (
              <>
                <div className="mt-1 text-2xl font-bold text-foreground">{formatMoney(data.last_settlement.amount)} د.ل</div>
                <div className="text-xs text-muted">{formatDateTime(data.last_settlement.created_at)} · {data.last_settlement.receiver?.name}</div>
              </>
            ) : <div className="mt-1 text-muted">لم يتم أي تسكير</div>}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 print:hidden lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle>سجل العهدة</CardTitle></CardHeader>
          <CardContent>
            {entries.data.length === 0 ? <div className="text-sm text-muted">لا توجد حركات.</div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border text-muted">
                    <tr>
                      <th className="py-2 text-start font-medium">النوع</th>
                      <th className="py-2 text-start font-medium">البيان</th>
                      <th className="py-2 text-end font-medium">المبلغ</th>
                      <th className="py-2 text-end font-medium">العهدة بعدها</th>
                      <th className="py-2 text-end font-medium">الوقت</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {entries.data.map((entry) => {
                      const meta = custodyEntryTypes[entry.type] ?? { label: entry.type, variant: 'default' }
                      const link = referenceLink(entry)
                      return (
                        <tr key={entry.id}>
                          <td className="py-2.5"><Badge variant={meta.variant}>{meta.label}</Badge></td>
                          <td className="py-2.5 text-foreground">
                            {link ? <Link to={link} className="hover:text-primary">{entry.note}</Link> : entry.note ?? '-'}
                            {entry.created_by?.name && <div className="text-xs text-muted">بواسطة {entry.created_by.name}</div>}
                          </td>
                          <td className={`py-2.5 text-end font-bold ${Number(entry.amount) < 0 ? 'text-success' : 'text-warning'}`}><bdi>{Number(entry.amount) > 0 ? '+' : ''}{formatMoney(entry.amount)}</bdi></td>
                          <td className="py-2.5 text-end">{formatMoney(entry.balance_after)}</td>
                          <td className="py-2.5 text-end text-muted">{formatDateTime(entry.created_at)}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
            {entries.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between text-sm">
                <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>السابق</Button>
                <span className="text-muted">صفحة {entries.current_page} من {entries.last_page}</span>
                <Button variant="secondary" size="sm" disabled={page >= entries.last_page} onClick={() => setPage(page + 1)}>التالي</Button>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>عمليات التسكير</CardTitle></CardHeader>
          <CardContent>
            {data.settlements.length === 0 ? <div className="text-sm text-muted">لا توجد عمليات.</div> : (
              <ul className="divide-y divide-border text-sm">
                {data.settlements.map((s) => (
                  <li key={s.id} className="py-2.5">
                    <button onClick={() => setReceipt({ ...s, delegate: data.delegate })} className="flex w-full items-center justify-between hover:text-primary">
                      <bdi className="font-mono text-xs">{s.reference_number}</bdi>
                      <span className="font-bold">{formatMoney(s.amount)} د.ل</span>
                    </button>
                    <div className="mt-0.5 text-xs text-muted">{formatDateTime(s.created_at)} · استلمها {s.receiver?.name ?? '-'}</div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <Modal title="تسكير حساب المندوب" open={!!settle} onClose={() => setSettle(null)}>
        {settle && (
          <form onSubmit={submitSettle} className="space-y-4">
            <div className="rounded-lg bg-background p-3 text-sm">
              العهدة الحالية: <span className="font-bold">{formatMoney(balance)} د.ل</span>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">المبلغ المستلم من المندوب (د.ل)</label>
              <input type="number" min="0.01" max={balance} step="0.01" className={inputClass} value={settle.amount} onChange={(e) => setSettle({ ...settle, amount: e.target.value })} required />
              {Number(settle.amount) < balance && <div className="mt-1 text-xs text-warning">سيبقى على المندوب {formatMoney(balance - Number(settle.amount || 0))} د.ل</div>}
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">ملاحظة (اختياري)</label>
              <input className={inputClass} value={settle.note} onChange={(e) => setSettle({ ...settle, note: e.target.value })} placeholder="مثال: تسليم نهاية الوردية" />
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setSettle(null)}>إلغاء</Button>
              <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'تأكيد الاستلام'}</Button>
            </div>
          </form>
        )}
      </Modal>

      <Modal title="إيصال استلام عهدة" open={!!receipt} onClose={() => setReceipt(null)}>
        {receipt && (
          <div className="space-y-3 text-sm">
            <div className="invoice-page rounded-lg border border-border bg-white p-5 text-stone-800">
              <div className="flex items-center justify-between border-b border-stone-200 pb-3">
                <div className="font-extrabold">الساحل لمستلزمات المقاهي</div>
                <bdi className="font-mono text-xs">{receipt.reference_number}</bdi>
              </div>
              <div className="py-4 text-center">
                <div className="text-xs text-stone-500">استلمنا من المندوب</div>
                <div className="text-lg font-bold">{receipt.delegate?.name ?? data.delegate.name}</div>
                <div className="mt-2 text-3xl font-black text-primary">{formatMoney(receipt.amount)} د.ل</div>
              </div>
              <div className="space-y-1 border-t border-stone-200 pt-3 text-xs">
                <div className="flex justify-between"><span className="text-stone-500">العهدة قبل</span><span>{formatMoney(receipt.custody_before)} د.ل</span></div>
                <div className="flex justify-between"><span className="text-stone-500">العهدة بعد</span><span className="font-bold">{formatMoney(receipt.custody_after)} د.ل</span></div>
                <div className="flex justify-between"><span className="text-stone-500">التاريخ</span><span>{formatDateTime(receipt.created_at)}</span></div>
                <div className="flex justify-between"><span className="text-stone-500">المستلم</span><span>{receipt.receiver?.name ?? '-'}</span></div>
                {receipt.note && <div className="flex justify-between"><span className="text-stone-500">ملاحظة</span><span>{receipt.note}</span></div>}
              </div>
              <div className="mt-8 grid grid-cols-2 gap-6 text-xs text-stone-500">
                <div className="border-t border-dashed border-stone-400 pt-1">توقيع المندوب</div>
                <div className="border-t border-dashed border-stone-400 pt-1">توقيع المستلم</div>
              </div>
            </div>
            <div className="flex justify-end gap-2 print:hidden">
              <Button variant="secondary" onClick={() => setReceipt(null)}>إغلاق</Button>
              <Button variant="primary" onClick={() => window.print()}><Printer className="h-4 w-4" /> طباعة</Button>
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}
