import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useApiResource, useApiList } from '../hooks/useApiResource'
import { useModulePermission } from '../hooks/usePermission'
import { useAuth } from '../context/AuthContext'
import client from '../api/client'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import MapPicker from '../components/MapPicker'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { FilterSelect } from '../components/ui/TableFilters'

const initial = {
  user_id: '',
  name: '',
  city: '',
  street: '',
  latitude: '',
  longitude: '',
  hex_id: '',
  delivery_zone_id: '',
  contact_phones: '',
}

export default function Addresses() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const isCustomer = user?.user_type?.name === 'customer'
  const [searchParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const [filterUser, setFilterUser] = useState(searchParams.get('user_id') || '')
  const { items, loading, error, pagination, setPage, create, update, remove, confirmDialog } = useApiResource('/addresses', { search, user_id: filterUser })
  const users = useApiList('/users?per_page=10000')
  const zones = useApiList('/delivery-zones?per_page=10000')
  const [modal, setModal] = useState(false)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const { canCreate, canEdit, canDelete } = useModulePermission('CUSTOMER_BRANCHES')

  const openCreate = () => {
    setForm({ ...initial, user_id: isCustomer ? user?.id ?? '' : '' })
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      user_id: item.user_id ?? '',
      latitude: item.latitude ?? '',
      longitude: item.longitude ?? '',
      hex_id: item.delivery_zone?.hex_id ?? '',
      delivery_zone_id: item.delivery_zone_id ?? '',
      contact_phones: (item.contact_phones ?? []).join('\n'),
    })
    setEditing(item)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
  }

  const ensureDeliveryZone = async () => {
    const lat = Number(form.latitude)
    const lng = Number(form.longitude)
    const hexId = form.hex_id
    if (!hexId || !lat || !lng) {
      return form.delivery_zone_id || null
    }

    const existing = zones.find((z) => z.hex_id === hexId)
    if (existing) return existing.id

    const res = await client.post('/delivery-zones', {
      hex_id: hexId,
      name: `منطقة ${hexId}`,
      delivery_price: 0,
      latitude: lat,
      longitude: lng,
      is_active: true,
    })
    return res.data?.data?.id ?? res.data?.id ?? null
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const deliveryZoneId = await ensureDeliveryZone()
      const data = {
        ...form,
        latitude: Number(form.latitude),
        longitude: Number(form.longitude),
        delivery_zone_id: deliveryZoneId,
        contact_phones: form.contact_phones.split('\n').map((p) => p.trim()).filter(Boolean),
      }
      if (!data.street) data.street = null
      if (!data.city) data.city = null
      delete data.hex_id
      if (editing) await update(editing.id, data)
      else await create(data)
      close()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'user', label: 'المستخدم', render: (r) => r.user?.name ?? '-' },
    { key: 'city', label: 'المدينة' },
    { key: 'street', label: 'الشارع' },
    { key: 'contact_phones', label: 'أرقام التواصل', render: (r) => (r.contact_phones ?? []).join('، ') || '-' },
    { key: 'delivery_zone', label: 'المنطقة', render: (r) => r.delivery_zone?.name || '-' },
  ]

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">العناوين</h1>
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="بحث..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
          <FilterSelect
            label="المستخدم"
            value={filterUser}
            onChange={setFilterUser}
            options={users.map((u) => ({ value: u.id, label: u.name }))}
          />
          {canCreate && <Button variant="primary" onClick={openCreate}>إضافة عنوان</Button>}
        </div>
      </header>
      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
      {confirmDialog}
      <DataTable
        columns={columns}
        rows={items}
        loading={loading}
        pagination={pagination}
        onPageChange={setPage}
        emptyText="لا توجد عناوين."
        onRowClick={(row) => navigate(`/addresses/${row.id}`)}
        actions={(row) => (
          <>
            {canEdit && <Button variant="secondary" size="sm" onClick={() => openEdit(row)}>تعديل</Button>}
            {canDelete && <Button variant="danger" size="sm" onClick={() => remove(row.id)}>حذف</Button>}
          </>
        )}
      />
      <Modal title={editing ? 'تعديل عنوان' : 'إضافة عنوان'} open={modal} onClose={close}>
        <form onSubmit={handleSubmit} className="space-y-4">
          {!isCustomer && (
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">المستخدم</label>
              <select
                className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
                value={form.user_id}
                onChange={(e) => setForm({ ...form, user_id: e.target.value })}
                required
              >
                <option value="">اختر المستخدم</option>
                {users.map((u) => (
                  <option key={u.id} value={u.id}>{u.name}</option>
                ))}
              </select>
            </div>
          )}
          <Input
            label="اسم العنوان"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <Input
            label="المدينة"
            value={form.city || ''}
            onChange={(e) => setForm({ ...form, city: e.target.value })}
          />
          <Input
            label="الشارع"
            value={form.street || ''}
            onChange={(e) => setForm({ ...form, street: e.target.value })}
          />
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">أرقام التواصل (رقم في كل سطر)</label>
            <textarea
              className="w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
              rows={3}
              value={form.contact_phones}
              onChange={(e) => setForm({ ...form, contact_phones: e.target.value })}
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">الموقع ومنطقة التوصيل</label>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="secondary" size="sm" onClick={() => setPickerOpen(true)}>
                {form.latitude && form.longitude ? 'تغيير على الخريطة' : 'اختيار على الخريطة'}
              </Button>
              <span className="text-sm text-muted">
                {form.latitude && form.longitude
                  ? `${Number(form.latitude).toFixed(5)}, ${Number(form.longitude).toFixed(5)}`
                  : 'لم يُختار موقع'}
              </span>
            </div>

          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            <Button type="button" variant="secondary" onClick={close}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ'}</Button>
          </div>
        </form>
      </Modal>
      <MapPicker
        open={pickerOpen}
        onClose={() => setPickerOpen(false)}
        initial={form.latitude && form.longitude ? { lat: Number(form.latitude), lng: Number(form.longitude) } : null}
        zones={zones}
        onSelect={({ lat, lng, hexId }) => setForm({ ...form, latitude: String(lat), longitude: String(lng), hex_id: hexId })}
      />
    </>
  )
}
