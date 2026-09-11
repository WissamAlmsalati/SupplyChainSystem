import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import client from '../api/client'

export default function CafeRegistrations() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { items, loading, error, pagination, setPage, fetch } = useApiResource('/cafe-registrations/pending', { search })
  const [processing, setProcessing] = useState(null)
  const [detail, setDetail] = useState(null)
  const [confirm, setConfirm] = useState(null) // { type: 'approve' | 'reject', cafe }

  const runAction = async (type, cafe) => {
    setProcessing(cafe.id)
    try {
      await client.post(`/cafe-registrations/${cafe.id}/${type}`)
      await fetch()
    } finally {
      setProcessing(null)
    }
  }

  const handleApprove = (cafe) => setConfirm({ type: 'approve', cafe })
  const handleReject = (cafe) => setConfirm({ type: 'reject', cafe })

  const columns = [
    {
      key: 'image_url',
      label: 'الصورة',
      render: (r) =>
        r.image_url ? (
          <img src={r.image_url} alt="" className="h-10 w-10 rounded object-cover" />
        ) : (
          <span className="text-muted">-</span>
        ),
    },
    { key: 'name', label: 'اسم المقهى' },
    { key: 'contact_info', label: 'وسيلة التواصل' },
    { key: 'address', label: 'العنوان' },
    {
      key: 'app_users',
      label: 'المستخدم',
      render: (r) => r.app_users?.[0]?.name ?? r.app_users?.[0]?.phone ?? '-',
    },
    {
      key: 'status',
      label: 'الحالة',
      render: () => <Badge variant="warning">قيد المراجعة</Badge>,
    },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">طلبات تسجيل المقاهي</h1>
          <p className="mt-1 text-sm text-muted">المقاهي المسجلة عبر التطبيق والمنتظرة قبول الإدارة</p>
        </div>
        <input
          type="text"
          placeholder="بحث..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
        />
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد طلبات تسجيل بانتظار المراجعة."
        onRowClick={(row) => setDetail(row)}
        actions={(row) => (
          <>
            <Button
              variant="primary"
              size="sm"
              disabled={processing === row.id}
              onClick={() => handleApprove(row)}
            >
              قبول
            </Button>
            <Button
              variant="danger"
              size="sm"
              disabled={processing === row.id}
              onClick={() => handleReject(row)}
            >
              رفض
            </Button>
          </>
        )}
      />

      <Modal open={!!detail} onClose={() => setDetail(null)} title="تفاصيل طلب التسجيل">
        {detail && (
          <div className="space-y-4 text-sm">
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">اسم المقهى</span>
              <span className="col-span-2 font-medium text-foreground">{detail.name}</span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">وسيلة التواصل</span>
              <span className="col-span-2 text-foreground">{detail.contact_info || '-'}</span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">العنوان</span>
              <span className="col-span-2 text-foreground">{detail.address || '-'}</span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">الموقع</span>
              <span className="col-span-2 text-foreground">
                {detail.latitude && detail.longitude ? `${detail.latitude}, ${detail.longitude}` : '-'}
              </span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">المستخدم</span>
              <span className="col-span-2 text-foreground">
                {detail.app_users?.[0]?.name || detail.app_users?.[0]?.phone || '-'}
              </span>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <span className="text-muted">تاريخ الطلب</span>
              <span className="col-span-2 text-foreground">
                {detail.created_at ? new Date(detail.created_at).toLocaleString('en-GB') : '-'}
              </span>
            </div>
            <div className="flex items-center justify-end gap-2 pt-2">
              <Button variant="secondary" onClick={() => setDetail(null)}>
                إغلاق
              </Button>
              <Button
                variant="primary"
                disabled={processing === detail.id}
                onClick={() => {
                  handleApprove(detail)
                  setDetail(null)
                }}
              >
                قبول
              </Button>
              <Button
                variant="danger"
                disabled={processing === detail.id}
                onClick={() => {
                  handleReject(detail)
                  setDetail(null)
                }}
              >
                رفض
              </Button>
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        open={confirm !== null}
        title={confirm?.type === 'reject' ? 'تأكيد الرفض' : 'تأكيد الموافقة'}
        message={
          confirm?.type === 'reject'
            ? `هل تريد رفض طلب تسجيل "${confirm?.cafe?.name}"؟ سيتم حذف المقهى والمستخدم المرتبط.`
            : `هل تريد الموافقة على طلب تسجيل "${confirm?.cafe?.name}"؟`
        }
        confirmLabel={confirm?.type === 'reject' ? 'رفض' : 'موافقة'}
        loading={processing !== null}
        onCancel={() => setConfirm(null)}
        onConfirm={async () => {
          const { type, cafe } = confirm
          setConfirm(null)
          await runAction(type, cafe)
        }}
      />
    </>
  )
}
