import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowRight, Plus, Minus, FileDown } from 'lucide-react'
import client from '../api/client'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import Modal from '../components/Modal'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { PageSkeleton } from '../components/ui/Skeleton'
import { useModulePermission } from '../hooks/usePermission'
import { walletTransactionTypes, topupMethods, topupStatuses, formatMoney, formatDateTime } from '../lib/wallet'
import { downloadFile, downloadError } from '../lib/download'

const inputClass = 'w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'

export default function WalletDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { canEdit } = useModulePermission('WALLETS')
  const [wallet, setWallet] = useState(null)
  const [transactions, setTransactions] = useState({ data: [], current_page: 1, last_page: 1 })
  const normalizePage = (payload) => ({ data: payload?.data ?? [], ...(payload?.meta ?? payload ?? {}) })
  const [page, setPage] = useState(1)
  const [type, setType] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [adjust, setAdjust] = useState(null)
  const [saving, setSaving] = useState(false)

  const loadWallet = async () => {
    const res = await client.get(`/wallets/${id}`)
    setWallet(res.data?.data ?? res.data)
  }

  const loadTransactions = async () => {
    const res = await client.get(`/wallets/${id}/transactions`, { params: { page, type: type || undefined } })
    setTransactions(normalizePage(res.data))
  }

  useEffect(() => {
    setLoading(true)
    loadWallet().catch((err) => setError(err.response?.data?.message || 'فشل تحميل المحفظة')).finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    loadTransactions().catch(() => {})
  }, [id, page, type])

  const submitAdjust = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const amount = Math.abs(Number(adjust.amount)) * (adjust.direction === 'debit' ? -1 : 1)
      await client.postOnce(`/wallets/${id}/adjust`, { amount, note: adjust.note })
      setAdjust(null)
      await Promise.all([loadWallet(), loadTransactions()])
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تعديل الرصيد')
    } finally {
      setSaving(false)
    }
  }

  const toggleActive = async () => {
    if (!window.confirm(wallet.is_active ? 'إيقاف المحفظة يمنع الدفع منها. متأكد؟' : 'تفعيل المحفظة؟')) return
    await client.post(`/wallets/${id}/toggle-active`)
    await loadWallet()
  }

  if (loading) return <PageSkeleton />
  if (!wallet) return <div className="text-danger">{error || 'المحفظة غير موجودة.'}</div>

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <button onClick={() => navigate('/wallets')} className="mt-1 rounded-md border border-border p-2 text-muted hover:bg-background" aria-label="رجوع">
            <ArrowRight className="h-4 w-4" />
          </button>
          <div>
            <h1 className="text-2xl font-extrabold text-foreground">محفظة {wallet.user?.name}</h1>
            <p className="mt-1 text-sm text-muted">
              <Link to={`/users/${wallet.user_id}`} className="hover:text-primary"><bdi>{wallet.user?.mobile_number}</bdi></Link>
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => downloadFile(`/reports/wallet/${id}`, { all: 1, format: 'pdf' }).catch(async (e) => setError(await downloadError(e)))}><FileDown className="h-4 w-4" /> كشف حساب PDF</Button>
          {canEdit && (<>
            <Button variant="primary" onClick={() => setAdjust({ direction: 'credit', amount: '', note: '' })}><Plus className="h-4 w-4" /> إضافة رصيد</Button>
            <Button variant="secondary" onClick={() => setAdjust({ direction: 'debit', amount: '', note: '' })}><Minus className="h-4 w-4" /> خصم رصيد</Button>
            <Button variant="secondary" onClick={toggleActive}>{wallet.is_active ? 'إيقاف المحفظة' : 'تفعيل المحفظة'}</Button>
          </>)}
        </div>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mb-6 rounded-xl bg-primary p-6 text-primary-foreground shadow-sm">
        <div className="text-sm opacity-80">الرصيد الحالي</div>
        <div className="mt-1 text-4xl font-black">{formatMoney(wallet.balance)} <span className="text-lg font-semibold">د.ل</span></div>
        {!wallet.is_active && <Badge variant="danger" className="mt-3">المحفظة موقوفة</Badge>}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>سجل الحركات</CardTitle>
            <select value={type} onChange={(e) => { setType(e.target.value); setPage(1) }} className="rounded-md border border-border-strong bg-surface px-2 py-1 text-sm">
              <option value="">الكل</option>
              {Object.entries(walletTransactionTypes).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </select>
          </CardHeader>
          <CardContent>
            {transactions.data.length === 0 ? (
              <div className="text-sm text-muted">لا توجد حركات.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border text-muted">
                    <tr>
                      <th className="py-2 text-start font-medium">النوع</th>
                      <th className="py-2 text-start font-medium">البيان</th>
                      <th className="py-2 text-end font-medium">المبلغ</th>
                      <th className="py-2 text-end font-medium">الرصيد بعدها</th>
                      <th className="py-2 text-end font-medium">الوقت</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {transactions.data.map((t) => {
                      const meta = walletTransactionTypes[t.type] ?? { label: t.type, variant: 'default' }
                      const orderLink = t.reference_type?.endsWith('Order') ? `/orders/${t.reference_id}` : null
                      return (
                        <tr key={t.id}>
                          <td className="py-2.5"><Badge variant={meta.variant}>{meta.label}</Badge></td>
                          <td className="py-2.5 text-foreground">
                            {orderLink ? <Link to={orderLink} className="hover:text-primary">{t.note}</Link> : t.note ?? '-'}
                            {t.created_by?.name && <div className="text-xs text-muted">بواسطة {t.created_by.name}</div>}
                          </td>
                          <td className={`py-2.5 text-end font-bold ${Number(t.amount) < 0 ? 'text-danger' : 'text-success'}`}><bdi>{Number(t.amount) > 0 ? '+' : ''}{formatMoney(t.amount)}</bdi></td>
                          <td className="py-2.5 text-end text-foreground">{formatMoney(t.balance_after)}</td>
                          <td className="py-2.5 text-end text-muted">{formatDateTime(t.created_at)}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
            {transactions.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between text-sm">
                <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>السابق</Button>
                <span className="text-muted">صفحة {transactions.current_page} من {transactions.last_page}</span>
                <Button variant="secondary" size="sm" disabled={page >= transactions.last_page} onClick={() => setPage(page + 1)}>التالي</Button>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>آخر طلبات الشحن</CardTitle></CardHeader>
          <CardContent>
            {(wallet.topups ?? []).length === 0 ? (
              <div className="text-sm text-muted">لا توجد طلبات.</div>
            ) : (
              <ul className="divide-y divide-border text-sm">
                {wallet.topups.map((t) => (
                  <li key={t.id} className="py-2.5">
                    <Link to={`/wallet-topups/${t.id}`} className="flex items-center justify-between hover:text-primary">
                      <span className="font-semibold">{formatMoney(t.amount)} د.ل</span>
                      <Badge variant={topupStatuses[t.status]?.variant}>{topupStatuses[t.status]?.label ?? t.status}</Badge>
                    </Link>
                    <div className="mt-0.5 text-xs text-muted">{topupMethods[t.method] ?? t.method} · {formatDateTime(t.created_at)}</div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <Modal title={adjust?.direction === 'debit' ? 'خصم من الرصيد' : 'إضافة إلى الرصيد'} open={!!adjust} onClose={() => setAdjust(null)}>
        {adjust && (
          <form onSubmit={submitAdjust} className="space-y-4">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">المبلغ (د.ل)</label>
              <input type="number" min="0.01" step="0.01" className={inputClass} value={adjust.amount} onChange={(e) => setAdjust({ ...adjust, amount: e.target.value })} required />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">السبب (يظهر في سجل الحركات)</label>
              <input className={inputClass} value={adjust.note} onChange={(e) => setAdjust({ ...adjust, note: e.target.value })} placeholder="مثال: إيداع نقدي في المكتب" required />
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setAdjust(null)}>إلغاء</Button>
              <Button type="submit" variant={adjust.direction === 'debit' ? 'danger' : 'primary'} disabled={saving}>{saving ? 'جاري الحفظ...' : 'تأكيد'}</Button>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}
