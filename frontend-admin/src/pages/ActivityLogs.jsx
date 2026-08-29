import { useState } from 'react'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import DataTable from '../components/DataTable'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import Badge from '../components/ui/Badge'

const actionLabels = {
  created: 'إنشاء',
  updated: 'تعديل',
  deleted: 'حذف',
}

const actionVariants = {
  created: 'success',
  updated: 'primary',
  deleted: 'danger',
}

export default function ActivityLogs() {
  const [filters, setFilters] = useState({ search: '', action: '', entity_type: '' })
  const [appliedFilters, setAppliedFilters] = useState({})

  const buildPath = () => {
    const params = new URLSearchParams()
    if (appliedFilters.search) params.set('search', appliedFilters.search)
    if (appliedFilters.action) params.set('action', appliedFilters.action)
    if (appliedFilters.entity_type) params.set('entity_type', appliedFilters.entity_type)
    return `/activity-logs?${params.toString()}`
  }

  const { items, loading, error, pagination, setPage } = useApiResource(buildPath())

  const applyFilters = () => setAppliedFilters(filters)
  const resetFilters = () => {
    setFilters({ search: '', action: '', entity_type: '' })
    setAppliedFilters({})
  }

  const columns = [
    {
      key: 'created_at',
      label: 'التاريخ',
      render: (r) => r.created_at ? new Date(r.created_at).toLocaleString('en-US') : '-',
    },
    { key: 'user_name', label: 'المستخدم' },
    {
      key: 'action',
      label: 'العملية',
      render: (r) => <Badge variant={actionVariants[r.action] || 'default'}>{actionLabels[r.action] || r.action}</Badge>,
    },
    { key: 'entity_type', label: 'النوع' },
    { key: 'description', label: 'التفاصيل' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">سجل النشاطات</h1>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mb-4 grid gap-3 rounded-lg border border-border bg-surface p-4 sm:grid-cols-4">
        <Input
          placeholder="بحث..."
          value={filters.search}
          onChange={(e) => setFilters({ ...filters, search: e.target.value })}
        />
        <select
          className="w-full rounded-md border border-border-strong bg-background px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
          value={filters.action}
          onChange={(e) => setFilters({ ...filters, action: e.target.value })}
        >
          <option value="">كل العمليات</option>
          <option value="created">إنشاء</option>
          <option value="updated">تعديل</option>
          <option value="deleted">حذف</option>
        </select>
        <select
          className="w-full rounded-md border border-border-strong bg-background px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
          value={filters.entity_type}
          onChange={(e) => setFilters({ ...filters, entity_type: e.target.value })}
        >
          <option value="">كل الأنواع</option>
          <option value="Cafe">مقهى</option>
          <option value="CafeBranch">فرع مقهى</option>
          <option value="Category">تصنيف</option>
          <option value="Product">منتج</option>
          <option value="ProductVariant">متغير منتج</option>
          <option value="Supplier">مورد</option>
          <option value="Warehouse">مستودع</option>
          <option value="Inventory">مخزون</option>
          <option value="Order">طلب</option>
          <option value="PurchaseOrder">طلب شراء</option>
          <option value="DeliveryZone">منطقة توصيل</option>
          <option value="AppUser">مستخدم</option>
          <option value="UserType">نوع مستخدم</option>
          <option value="Permission">صلاحية</option>
          <option value="ProductImage">صورة منتج</option>
        </select>
        <div className="flex gap-2">
          <Button variant="primary" onClick={applyFilters}>تطبيق</Button>
          <Button variant="secondary" onClick={resetFilters}>إعادة</Button>
        </div>
      </div>

      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد سجلات نشاط."
      />
    </>
  )
}
