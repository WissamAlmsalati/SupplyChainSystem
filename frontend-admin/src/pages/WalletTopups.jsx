import { useEffect, useState } from 'react'
import { useSearchParams, useParams, useNavigate, Link } from 'react-router-dom'
import client from '../api/client'
import { useApiResource } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import Button from '../components/ui/Button'
import Modal from '../components/Modal'
import { FilterSelect } from '../components/ui/TableFilters'
import { topupMethods, topupStatuses, formatMoney, formatDateTime } from '../lib/wallet'

function TopupReview({ topup, canEdit, onDone, onClose }) {
  const [reason, setReason] = useState('')
  const [rejecting, setRejecting] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const act = async (action) => {
    setSaving(true)
    setError('')
    try {
      await client.post(`/wallet-topups/${topup.id}/${action}`, action === 'reject' ? { reason } : {})
      onDone()
    } catch (err) {
      setError(err.response?.data?.message || 'فشلت العملية')
    } finally {
      setSaving(false)
    }
  }

  const status = topupStatuses[topup.status] ?? { label: topup.status }
  const Row = ({ label, children }) => (
    <div className="flex justify-between gap-4 py-1.5"><span className="text-muted">{label}</span><span className="text-end text-foreground">{children ?? '-'}</span></div>
  )

  return (
    <div className="space-y-4 text-sm">
      <div className="rounded-lg bg-background p-4 text-center">
        <div className="text-3xl font-black text-foreground">{formatMoney(topup.amount)} د.ل</div>
        <Badge variant={status.variant} className="mt-2">{status.label}</Badge>
      </div>
      <div>
        <Row label="الزبون">{topup.user ? <Link to={`/users/${topup.user_id}`} className="font-medium hover:text-primary">{topup.user.name}</Link> : '-'}</Row>
        <Row label="الجوال"><bdi>{topup.user?.mobile_number}</bdi></Row>
        <Row label="الطريقة">{topupMethods[topup.method] ?? topup.method}</Row>
        {topup.reference_number && <Row label="رقم المرجع"><bdi>{topup.reference_number}</bdi></Row>}
        {topup.gateway_reference && <Row label="مرجع البوابة"><bdi>{topup.gateway_reference}</bdi></Row>}
        {topup.collector && <Row label="حصّله المندوب">{topup.collector.name}</Row>}
        {topup.order?.order_number && <Row label="الطلب"><Link to={`/orders/${topup.order_id}`} className="hover:text-primary"><bdi>{topup.order.order_number}</bdi></Link></Row>}
        {topup.note && <Row label="ملاحظة">{topup.note}</Row>}
        <Row label="تاريخ الطلب">{formatDateTime(topup.created_at)}</Row>
        {topup.reviewer && <Row label="راجعه">{topup.reviewer.name} · {formatDateTime(topup.reviewed_at)}</Row>}
        {topup.rejection_reason && <Row label="سبب الرفض">{topup.rejection_reason}</Row>}
      </div>
      <div>
        <div className="mb-1.5 flex items-center justify-between">
          <span className="text-muted">إيصال الحوالة</span>
          {topup.receipt_url && (
            <a href={topup.receipt_url} target="_blank" rel="noreferrer" className="text-xs font-medium text-primary hover:underline">
              فتح {topup.receipt_type === 'pdf' ? 'ملف PDF' : 'الصورة'} في نافذة جديدة
            </a>
          )}
        </div>
        {!topup.receipt_url ? (
          <div className={`rounded-lg border border-dashed px-3 py-4 text-center text-xs ${topup.method === 'bank_transfer' ? 'border-danger/40 text-danger' : 'border-border text-muted'}`}>
            {topup.method === 'bank_transfer' ? 'لم يُرفق إيصال مع هذه الحوالة' : 'لا يوجد إيصال (غير مطلوب لهذه الطريقة)'}
          </div>
        ) : topup.receipt_type === 'pdf' ? (
          <object data={topup.receipt_url} type="application/pdf" className="h-96 w-full rounded-lg border border-border bg-background">
            <a href={topup.receipt_url} target="_blank" rel="noreferrer" className="block p-4 text-center text-primary">عرض ملف PDF</a>
          </object>
        ) : (
          <a href={topup.receipt_url} target="_blank" rel="noreferrer" className="block">
            <img src={topup.receipt_url} alt="إيصال الحوالة" className="max-h-96 w-full rounded-lg border border-border bg-background object-contain" />
          </a>
        )}
      </div>
      {error && <div className="rounded-md bg-danger-soft px-3 py-2 text-danger">{error}</div>}
      {canEdit && topup.status === 'pending' && (
        rejecting ? (
          <div className="space-y-2">
            <input
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="سبب الرفض (يصل للزبون)"
              className="w-full rounded-md border border-border-strong bg-surface px-3 py-2"
            />
            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setRejecting(false)}>رجوع</Button>
              <Button variant="danger" disabled={saving || !reason.trim()} onClick={() => act('reject')}>تأكيد الرفض</Button>
            </div>
          </div>
        ) : (
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setRejecting(true)}>رفض</Button>
            <Button variant="primary" disabled={saving} onClick={() => { if (window.confirm(`إضافة ${formatMoney(topup.amount)} د.ل إلى محفظة ${topup.user?.name}؟`)) act('approve') }}>
              قبول وإضافة الرصيد
            </Button>
          </div>
        )
      )}
      {!(canEdit && topup.status === 'pending') && (
        <div className="flex justify-end"><Button variant="secondary" onClick={onClose}>إغلاق</Button></div>
      )}
    </div>
  )
}

export default function WalletTopups() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState(searchParams.get('status') ?? 'pending')
  const [method, setMethod] = useState('')
  const { items, loading, error, pagination, setPage, fetch } = useApiResource('/wallet-topups', { search, status, method })
  const { canEdit } = useModulePermission('WALLET_TOPUPS')
  const [selected, setSelected] = useState(null)

  const open = async (topupId) => {
    const res = await client.get(`/wallet-topups/${topupId}`)
    setSelected(res.data?.data ?? res.data)
  }

  useEffect(() => {
    if (id) open(id).catch(() => {})
  }, [id])

  const close = () => {
    setSelected(null)
    if (id) navigate('/wallet-topups', { replace: true })
  }

  const columns = [
    { key: 'id', label: '#', render: (r) => `#${r.id}` },
    { key: 'user', label: 'الزبون', render: (r) => <span className="font-medium">{r.user?.name ?? '-'}</span> },
    { key: 'amount', label: 'المبلغ', render: (r) => <span className="font-bold">{formatMoney(r.amount)} د.ل</span> },
    { key: 'method', label: 'الطريقة', render: (r) => topupMethods[r.method] ?? r.method },
    { key: 'reference_number', label: 'المرجع', render: (r) => <bdi>{r.reference_number || r.gateway_reference || (r.collector ? `مندوب: ${r.collector.name}` : '-')}</bdi> },
    { key: 'receipt', label: 'الإيصال', render: (r) => (r.receipt_url ? <Badge variant="info">{r.receipt_type === 'pdf' ? 'PDF' : 'صورة'}</Badge> : r.method === 'bank_transfer' ? <Badge variant="danger">بدون</Badge> : '-') },
    { key: 'status', label: 'الحالة', render: (r) => <Badge variant={topupStatuses[r.status]?.variant}>{topupStatuses[r.status]?.label ?? r.status}</Badge> },
    { key: 'created_at', label: 'التاريخ', render: (r) => formatDateTime(r.created_at) },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">طلبات شحن المحافظ</h1>
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder="بحث بالاسم، الجوال أو المرجع..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <FilterSelect label="الحالة" value={status} onChange={setStatus} options={Object.entries(topupStatuses).map(([value, v]) => ({ value, label: v.label }))} />
          <FilterSelect label="الطريقة" value={method} onChange={setMethod} options={Object.entries(topupMethods).map(([value, label]) => ({ value, label }))} />
        </div>
      </header>
      {error && <div className="my-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      <div className="mt-6">
        <DataTable
          columns={columns}
          rows={items}
          loading={loading}
          pagination={pagination}
          onPageChange={setPage}
          emptyText="لا توجد طلبات شحن."
          actions={(row) => (
            <Button variant={row.status === 'pending' && canEdit ? 'primary' : 'secondary'} size="sm" onClick={() => open(row.id)}>
              {row.status === 'pending' && canEdit ? 'مراجعة' : 'عرض'}
            </Button>
          )}
        />
      </div>
      <Modal title={selected ? `طلب شحن #${selected.id}` : ''} open={!!selected} onClose={close} size="lg">
        {selected && <TopupReview topup={selected} canEdit={canEdit} onClose={close} onDone={() => { close(); fetch() }} />}
      </Modal>
    </>
  )
}
