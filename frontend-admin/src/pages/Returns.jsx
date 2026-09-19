import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Undo2 } from 'lucide-react'
import { useApiResource } from '../hooks/useApiResource'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import { Card, CardContent } from '../components/ui/Card'
import { formatMoney, formatDateTime } from '../lib/wallet'

const REFUND_METHODS = { wallet: 'إلى المحفظة', cash: 'نقداً', none: 'بدون استرداد' }

// Goods that came back after delivery. A return is recorded from its order's
// page, where the delivered quantities are; this page is the register.
export default function Returns() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [pendingOnly, setPendingOnly] = useState(false)
  const { items, loading, error, pagination, setPage, fetch } = useApiResource('/returns', { search, refund_pending: pendingOnly ? 1 : '' })
  // Handing over a cash refund: by the office, or by a delegate out of their custody.
  const [paying, setPaying] = useState(null)
  const [delegates, setDelegates] = useState([])
  const [delegateId, setDelegateId] = useState('')
  const [payError, setPayError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (paying && !delegates.length) client.get('/delegates', { params: { per_page: 100 } }).then((r) => setDelegates(r.data?.data ?? [])).catch(() => {})
  }, [paying, delegates.length])

  const openPay = (row) => { setPayError(''); setDelegateId(''); setPaying(row) }
  const submitPay = async (e) => {
    e.preventDefault()
    setSaving(true)
    setPayError('')
    try {
      await client.postOnce(`/returns/${paying.id}/pay-refund`, { delegate_id: delegateId ? Number(delegateId) : null })
      setPaying(null)
      fetch()
    } catch (err) {
      const errors = err.response?.data?.errors
      setPayError(Object.values(errors ?? {})[0]?.[0] || err.response?.data?.message || 'فشل تسجيل التسليم')
    } finally {
      setSaving(false)
    }
  }
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
        ? <span>{formatMoney(r.refund_amount)} د.ل <Badge variant={r.refund_pending ? 'danger' : r.refund_method === 'wallet' ? 'success' : 'default'}>{r.refund_pending ? 'نقداً، لم يُسلَّم' : REFUND_METHODS[r.refund_method]}</Badge></span>
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
          <button type="button" onClick={() => setPendingOnly((v) => !v)} className={`rounded-lg border px-4 py-2 text-right ${pendingOnly ? 'border-danger bg-danger-soft' : 'border-border'}`}>
            <div className="text-sm text-muted">نقد مستحق للزبائن لم يُسلَّم</div>
            <div className={`text-2xl font-extrabold ${Number(summary.refund_pending) > 0 ? 'text-danger' : 'text-foreground'}`}>{formatMoney(summary.refund_pending ?? 0)} د.ل</div>
          </button>
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
        actions={(row) => (row.refund_pending ? <Button variant="primary" size="sm" onClick={() => openPay(row)}>تسليم الاسترداد</Button> : null)}
      />

      <Modal title="تسليم استرداد نقدي" open={!!paying} onClose={() => setPaying(null)}>
        {paying && (
          <form onSubmit={submitPay} className="space-y-4">
            <p className="text-sm text-foreground">
              تسليم <span className="font-bold">{formatMoney(paying.refund_amount)} د.ل</span> إلى {paying.order?.user?.name ?? 'الزبون'} عن مرتجع الطلب <bdi>{paying.order?.order_number}</bdi>.
            </p>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">من سلّم المبلغ؟</label>
              <select className="w-full rounded-lg border border-border px-3 py-2 text-sm" value={delegateId} onChange={(e) => setDelegateId(e.target.value)}>
                <option value="">المكتب</option>
                {delegates.map((d) => <option key={d.id} value={d.id}>مندوب: {d.name}</option>)}
              </select>
              <p className="mt-1.5 text-xs text-muted">عند اختيار مندوب يُخصم المبلغ من عهدته.</p>
            </div>
            {payError && <div className="rounded-lg border border-danger/20 bg-danger-soft px-3 py-2 text-sm text-danger">{payError}</div>}
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setPaying(null)}>إلغاء</Button>
              <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'تأكيد التسليم'}</Button>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}
