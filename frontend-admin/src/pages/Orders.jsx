import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useModulePermission } from '../hooks/usePermission'
import { useApiResource } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import QuickOrderModal from '../components/QuickOrderModal'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import { StatusBadge } from '../lib/status'
import { FilterSelect, FilterDate } from '../components/ui/TableFilters'

export default function Orders() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const { items, loading, error, pagination, setPage, fetch } = useApiResource('/orders', { search, status: filterStatus, date_from: dateFrom, date_to: dateTo })
  const [quickOpen, setQuickOpen] = useState(false)
  const { canCreate } = useModulePermission('ORDERS')

  const columns = [
    { key: 'order_number', label: 'رقم الطلب', render: (r) => r.order_number ?? `#${r.id}` },
    { key: 'status', label: 'الحالة', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'source',
      label: 'المصدر',
      render: (r) =>
        r.source === 'add order from dashboard' ? (
          <Badge variant="primary">لوحة التحكم</Badge>
        ) : (
          <Badge variant="default">مستخدم التطبيق</Badge>
        ),
    },
    { key: 'total_amount', label: 'الإجمالي' },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    { key: 'branch', label: 'الفرع', render: (r) => r.branch?.name ?? '-' },
    { key: 'delegate', label: 'المندوب', render: (r) => r.delegate?.name ?? <span className="text-muted">-</span> },
    { key: 'created_at', label: 'تاريخ الإنشاء', render: (r) => r.created_at ? new Date(r.created_at).toLocaleDateString('en-US') : '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الطلبات</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <FilterSelect
            label="الحالة"
            value={filterStatus}
            onChange={setFilterStatus}
            options={[
              { value: 'pending', label: 'قيد الانتظار' },
              { value: 'confirmed', label: 'مؤكد' },
              { value: 'processing', label: 'قيد التجهيز' },
              { value: 'shipped', label: 'مشحون' },
              { value: 'delivered', label: 'تم التوصيل' },
              { value: 'completed', label: 'مكتمل' },
              { value: 'cancelled', label: 'ملغي' },
            ]}
          />
          <FilterDate label="من تاريخ" value={dateFrom} onChange={setDateFrom} />
          <FilterDate label="إلى تاريخ" value={dateTo} onChange={setDateTo} />
          {canCreate && (
            <Button variant="primary" onClick={() => setQuickOpen(true)}>
              + طلب جديد
            </Button>
          )}
        </div>
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
        onRowClick={(row) => navigate(`/orders/${row.id}`)}
      />

    </>
  )
}
