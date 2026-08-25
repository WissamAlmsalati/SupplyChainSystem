import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useModulePermission } from '../hooks/usePermission'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import QuickOrderModal from '../components/QuickOrderModal'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import client from '../api/client'

export default function Orders() {
  const navigate = useNavigate()
  const { items, loading, error, pagination, setPage, update, fetch } = useApiResource('/orders')
  const delegates = useApiList('/delegates')
  const [statusOrder, setStatusOrder] = useState(null)
  const [statusModal, setStatusModal] = useState(false)
  const [newStatus, setNewStatus] = useState('')
  const [delegateOrder, setDelegateOrder] = useState(null)
  const [delegateModal, setDelegateModal] = useState(false)
  const [selectedDelegate, setSelectedDelegate] = useState('')
  const [saving, setSaving] = useState(false)
  const [quickOpen, setQuickOpen] = useState(false)
  const { canCreate, canEdit } = useModulePermission('ORDERS')

  const openStatus = (order) => {
    setStatusOrder(order)
    setNewStatus(order.status || '')
    setStatusModal(true)
  }

  const closeStatus = () => {
    setStatusModal(false)
    setStatusOrder(null)
  }

  const saveStatus = async (e) => {
    e.preventDefault()
    if (!statusOrder) return
    setSaving(true)
    try {
      await update(statusOrder.id, { status: newStatus })
      closeStatus()
    } finally {
      setSaving(false)
    }
  }

  const openDelegate = (order) => {
    setDelegateOrder(order)
    setSelectedDelegate(order.delegate?.id ? String(order.delegate.id) : '')
    setDelegateModal(true)
  }

  const closeDelegate = () => {
    setDelegateModal(false)
    setDelegateOrder(null)
    setSelectedDelegate('')
  }

  const saveDelegate = async (e) => {
    e.preventDefault()
    if (!delegateOrder || !selectedDelegate) return
    setSaving(true)
    try {
      await client.post(`/orders/${delegateOrder.id}/assign-delegate`, { delegate_id: Number(selectedDelegate) })
      fetch()
      closeDelegate()
    } catch (err) {
      alert(err.response?.data?.message || 'فشل تعيين المندوب')
    } finally {
      setSaving(false)
    }
  }

  const statusLabels = {
    pending: 'معلّق',
    processing: 'قيد المعالجة',
    completed: 'مكتمل',
    delivered: 'تم التوصيل',
    cancelled: 'ملغي',
    failed: 'فاشل',
  }

  function statusVariant(status) {
    if (!status) return 'default'
    const s = String(status).toLowerCase()
    if (['completed', 'delivered', 'paid'].includes(s)) return 'success'
    if (['cancelled', 'failed'].includes(s)) return 'danger'
    if (['pending', 'processing'].includes(s)) return 'warning'
    return 'default'
  }

  const columns = [
    { key: 'id', label: 'الرقم', render: (r) => `#${r.id}` },
    { key: 'status', label: 'الحالة', render: (r) => <Badge variant={statusVariant(r.status)}>{statusLabels[r.status] || r.status || '-'}</Badge> },
    {
      key: 'source',
      label: 'المصدر',
      render: (r) =>
        r.source === 'add order from dashboard' ? (
          <Badge variant="primary">Dashboard</Badge>
        ) : (
          '-'
        ),
    },
    { key: 'total_amount', label: 'الإجمالي' },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    { key: 'branch', label: 'الفرع', render: (r) => r.branch?.name ?? '-' },
    { key: 'delegate', label: 'المندوب', render: (r) => r.delegate?.name ?? <span className="text-muted">-</span> },
    { key: 'created_at', label: 'تاريخ الإنشاء', render: (r) => r.created_at ? new Date(r.created_at).toLocaleDateString('ar-SA') : '-' },
  ]

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الطلبات</h1>
        {canCreate && (
          <Button variant="primary" onClick={() => setQuickOpen(true)}>
            + طلب جديد
          </Button>
        )}
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <QuickOrderModal open={quickOpen} onClose={() => setQuickOpen(false)} onCreated={fetch} />
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد طلبات."
        actions={(row) => (
          <>
            <Button variant="secondary" size="sm" onClick={() => navigate(`/orders/${row.id}`)}>عرض</Button>
            {canEdit && <Button variant="primary" size="sm" onClick={() => openStatus(row)}>الحالة</Button>}
            {canEdit && <Button variant="default" size="sm" onClick={() => openDelegate(row)}>مندوب</Button>}
          </>
        )}
      />

      <Modal title="تحديث الحالة" open={statusModal} onClose={closeStatus}>
        <form onSubmit={saveStatus} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الحالة</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={newStatus}
              onChange={(e) => setNewStatus(e.target.value)}
              required
            >
              <option value="">اختر الحالة</option>
              {Object.entries(statusLabels).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={closeStatus}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>

      <Modal title="تعيين مندوب" open={delegateModal} onClose={closeDelegate}>
        <form onSubmit={saveDelegate} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">المندوب</label>
            <select
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              value={selectedDelegate}
              onChange={(e) => setSelectedDelegate(e.target.value)}
              required
            >
              <option value="">اختر مندوب</option>
              {delegates.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={closeDelegate}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
