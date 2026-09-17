import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import client from '../api/client'

const TX_TYPES = {
  topup: { label: 'شحن', className: 'text-green-700 bg-green-50' },
  payment: { label: 'دفع طلب', className: 'text-amber-700 bg-amber-50' },
  refund: { label: 'استرجاع', className: 'text-blue-700 bg-blue-50' },
  adjustment: { label: 'تعديل', className: 'text-stone-700 bg-stone-100' },
}
const METHODS = { bank_transfer: 'تحويل بنكي', delegate_cash: 'نقداً عبر المندوب', gateway: 'دفع إلكتروني' }
const STATUSES = {
  pending: { label: 'قيد المراجعة', className: 'text-amber-700 bg-amber-50' },
  approved: { label: 'مقبول', className: 'text-green-700 bg-green-50' },
  rejected: { label: 'مرفوض', className: 'text-red-700 bg-red-50' },
  cancelled: { label: 'ملغي', className: 'text-stone-600 bg-stone-100' },
  failed: { label: 'فشل', className: 'text-red-700 bg-red-50' },
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '-'
}

const inputClass = 'w-full rounded-lg border border-border-strong bg-background px-3 py-2 text-sm text-foreground outline-none focus:border-primary'

export default function Wallet() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [wallet, setWallet] = useState(null)
  const [transactions, setTransactions] = useState([])
  const [topups, setTopups] = useState([])
  const [tab, setTab] = useState('transactions')
  const [mode, setMode] = useState(null)
  const [form, setForm] = useState({ amount: '', method: 'bank_transfer', reference_number: '', note: '' })
  const [receipt, setReceipt] = useState(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const load = async () => {
    const [w, t, r] = await Promise.all([
      client.get('/customer/wallet'),
      client.get('/customer/wallet/transactions', { params: { per_page: 50 } }),
      client.get('/customer/wallet/topups', { params: { per_page: 50 } }),
    ])
    setWallet(w.data?.data)
    setTransactions(t.data?.data ?? [])
    setTopups(r.data?.data ?? [])
  }

  useEffect(() => {
    load().catch(() => setError('فشل تحميل المحفظة'))
  }, [])

  // Back from the payment gateway checkout.
  useEffect(() => {
    const status = searchParams.get('status')
    if (!status) return
    if (status === 'approved') setSuccess('تم الدفع بنجاح وأُضيف المبلغ إلى رصيدك')
    else setError('لم تكتمل عملية الدفع')
    setSearchParams({}, { replace: true })
  }, [])

  const submitRequest = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    setSuccess('')
    try {
      const data = new FormData()
      data.append('amount', form.amount)
      data.append('method', form.method)
      if (form.reference_number) data.append('reference_number', form.reference_number)
      if (form.note) data.append('note', form.note)
      if (receipt) data.append('receipt', receipt)
      const res = await client.postForm('/customer/wallet/topups', data)
      setSuccess(res.data?.message || 'تم إرسال طلب الشحن')
      setMode(null)
      setForm({ amount: '', method: 'bank_transfer', reference_number: '', note: '' })
      setReceipt(null)
      setTab('topups')
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إرسال الطلب')
    } finally {
      setSaving(false)
    }
  }

  const payOnline = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const res = await client.post('/customer/wallet/topups/gateway', { amount: Number(form.amount) })
      window.location.href = res.data?.data?.checkout_url
    } catch (err) {
      setError(err.response?.data?.message || 'تعذر بدء الدفع الإلكتروني')
      setSaving(false)
    }
  }

  const cancelTopup = async (topup) => {
    if (!window.confirm('إلغاء طلب الشحن؟')) return
    try {
      await client.post(`/customer/wallet/topups/${topup.id}/cancel`)
      await load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الإلغاء')
    }
  }

  if (!wallet) {
    return <div className="pt-6"><div className="h-40 animate-pulse rounded-xl bg-border" /></div>
  }

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">محفظتي</h1>
        <p className="mt-1 text-muted">اشحن رصيدك وادفع طلباتك مباشرة من المحفظة</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {success && <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{success}</div>}

      <div className="mb-6 rounded-2xl bg-primary p-6 text-primary-foreground shadow-sm">
        <div className="text-sm opacity-80">الرصيد المتاح</div>
        <div className="mt-1 text-4xl font-black">{formatMoney(wallet.balance)} <span className="text-lg font-semibold">د.ل</span></div>
        {wallet.pending_topups > 0 && <div className="mt-2 text-sm opacity-90">لديك {wallet.pending_topups} طلب شحن قيد المراجعة</div>}
        {!wallet.is_active && <div className="mt-2 text-sm font-bold">المحفظة موقوفة، تواصل مع الإدارة</div>}
        <div className="mt-5 flex flex-wrap gap-2">
          <button onClick={() => setMode('online')} className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary hover:bg-white/90">شحن إلكتروني</button>
          <button onClick={() => setMode('request')} className="rounded-lg border border-white/60 px-4 py-2 text-sm font-semibold hover:bg-white/10">تحويل بنكي</button>
        </div>
      </div>

      {mode && (
        <form onSubmit={mode === 'online' ? payOnline : submitRequest} className="mb-6 space-y-4 rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold text-foreground">{mode === 'online' ? 'شحن إلكتروني' : 'شحن بتحويل بنكي'}</h2>
            <button type="button" onClick={() => setMode(null)} className="text-sm text-muted hover:text-foreground">إغلاق</button>
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المبلغ (د.ل)</label>
            <input type="number" min={wallet.min_topup} max={wallet.max_topup} step="0.01" required className={inputClass} value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
            <div className="mt-1 text-xs text-muted">من {wallet.min_topup} إلى {wallet.max_topup} د.ل</div>
          </div>
          {mode === 'request' && (
            <>
              <p className="text-xs text-muted">حوّل المبلغ لحساب الشركة ثم أرفق إيصال الحوالة. يُضاف الرصيد بعد مراجعة الإدارة. للدفع نقداً سلّم المبلغ للمندوب عند التوصيل.</p>
              {(
                <>
                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-muted">رقم مرجع التحويل</label>
                    <input required className={inputClass} value={form.reference_number} onChange={(e) => setForm({ ...form, reference_number: e.target.value })} />
                  </div>
                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-muted">إيصال الحوالة (صورة أو PDF)</label>
                    <label className={`flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-5 text-center text-sm ${receipt ? 'border-primary bg-primary/5' : 'border-border-strong hover:bg-background'}`}>
                      <input
                        type="file"
                        accept="image/*,application/pdf"
                        required
                        className="sr-only"
                        onChange={(e) => {
                          const file = e.target.files?.[0] ?? null
                          if (file && file.size > 5 * 1024 * 1024) {
                            setError('حجم الإيصال يجب ألا يتجاوز 5 ميجابايت')
                            e.target.value = ''
                            return
                          }
                          setReceipt(file)
                        }}
                      />
                      {receipt ? (
                        <>
                          <span className="font-semibold text-primary">{receipt.type === 'application/pdf' ? 'ملف PDF' : 'صورة'}: {receipt.name}</span>
                          <span className="text-xs text-muted">{(receipt.size / 1024).toFixed(0)} KB — اضغط للتغيير</span>
                        </>
                      ) : (
                        <>
                          <span className="font-semibold text-foreground">اضغط لرفع إيصال الحوالة</span>
                          <span className="text-xs text-muted">JPG أو PNG أو PDF — حتى 5 ميجابايت</span>
                        </>
                      )}
                    </label>
                  </div>
                </>
              )}
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">ملاحظة (اختياري)</label>
                <input className={inputClass} value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
              </div>
            </>
          )}
          <button type="submit" disabled={saving || (mode === 'request' && !receipt)} className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60">
            {saving ? 'جاري...' : mode === 'online' ? 'متابعة إلى الدفع' : 'إرسال الطلب'}
          </button>
        </form>
      )}

      <div className="mb-4 flex gap-2">
        {[['transactions', 'الحركات'], ['topups', 'طلبات الشحن']].map(([key, label]) => (
          <button key={key} onClick={() => setTab(key)} className={`rounded-full px-4 py-1.5 text-sm font-medium ${tab === key ? 'bg-primary text-primary-foreground' : 'border border-border bg-background text-foreground'}`}>
            {label}
          </button>
        ))}
      </div>

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        {tab === 'transactions' ? (
          transactions.length === 0 ? <div className="p-6 text-center text-sm text-muted">لا توجد حركات بعد.</div> : (
            <ul className="divide-y divide-border">
              {transactions.map((t) => (
                <li key={t.id} className="flex items-center justify-between gap-3 px-4 py-3">
                  <div>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${TX_TYPES[t.type]?.className ?? ''}`}>{TX_TYPES[t.type]?.label ?? t.type}</span>
                    <div className="mt-1 text-sm text-foreground">{t.note ?? '-'}</div>
                    <div className="text-xs text-muted">{formatDate(t.created_at)}</div>
                  </div>
                  <div className="text-end">
                    <div className={`font-bold ${Number(t.amount) < 0 ? 'text-red-600' : 'text-green-700'}`}><bdi>{Number(t.amount) > 0 ? '+' : ''}{formatMoney(t.amount)}</bdi></div>
                    <div className="text-xs text-muted">الرصيد: {formatMoney(t.balance_after)}</div>
                  </div>
                </li>
              ))}
            </ul>
          )
        ) : topups.length === 0 ? <div className="p-6 text-center text-sm text-muted">لا توجد طلبات شحن.</div> : (
          <ul className="divide-y divide-border">
            {topups.map((t) => (
              <li key={t.id} className="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                  <div className="font-semibold text-foreground">{formatMoney(t.amount)} د.ل</div>
                  <div className="text-xs text-muted">{METHODS[t.method] ?? t.method} · {formatDate(t.created_at)}</div>
                  {t.rejection_reason && <div className="text-xs text-red-600">السبب: {t.rejection_reason}</div>}
                  {t.receipt_url && <a href={t.receipt_url} target="_blank" rel="noreferrer" className="text-xs text-primary hover:underline">عرض الإيصال ({t.receipt_type === 'pdf' ? 'PDF' : 'صورة'})</a>}
                </div>
                <div className="flex items-center gap-2">
                  <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUSES[t.status]?.className ?? ''}`}>{STATUSES[t.status]?.label ?? t.status}</span>
                  {t.status === 'pending' && t.method !== 'gateway' && (
                    <button onClick={() => cancelTopup(t)} className="text-xs text-danger hover:underline">إلغاء</button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  )
}
