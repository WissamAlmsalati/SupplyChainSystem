import { useEffect, useState } from 'react'
import client from '../api/client'
import MapPicker from '../components/MapPicker'

const initial = {
  name: '',
  city: '',
  street: '',
  latitude: '',
  longitude: '',
  hex_id: '',
  delivery_zone_id: '',
  is_active: true,
}

export default function Branches() {
  const [branches, setBranches] = useState([])
  const [zones, setZones] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modal, setModal] = useState(false)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const [branchesRes, zonesRes] = await Promise.all([
        client.get('/cafe-branches?per_page=100'),
        client.get('/cafe/delivery-zones'),
      ])
      setBranches(branchesRes.data.data)
      setZones(zonesRes.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الفروع')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const openCreate = () => {
    setForm(initial)
    setEditing(null)
    setModal(true)
  }

  const openEdit = (item) => {
    setForm({
      ...initial,
      ...item,
      latitude: item.latitude ?? '',
      longitude: item.longitude ?? '',
      hex_id: item.delivery_zone?.hex_id ?? '',
      delivery_zone_id: item.delivery_zone_id ?? '',
    })
    setEditing(item)
    setModal(true)
  }

  const close = () => {
    setModal(false)
    setForm(initial)
    setEditing(null)
  }

  const ensureDeliveryZone = () => {
    const hexId = form.hex_id
    if (!hexId) {
      return form.delivery_zone_id || null
    }

    const existing = zones.find((z) => z.hex_id === hexId)
    return existing?.id ?? null
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.delivery_zone_id && !form.hex_id) {
      setError('اختر موقع الفرع على الخريطة ضمن منطقة توصيل مسعّرة.')
      return
    }
    setSaving(true)
    try {
      const deliveryZoneId = ensureDeliveryZone()
      if (!deliveryZoneId) {
        setError('الخلية المختارة ليست ضمن مناطق التوصيل المسعّرة.')
        return
      }
      const data = {
        ...form,
        latitude: Number(form.latitude),
        longitude: Number(form.longitude),
        delivery_zone_id: deliveryZoneId,
        is_active: Boolean(form.is_active),
      }
      if (!data.street) data.street = null
      if (!data.city) data.city = null
      delete data.hex_id

      if (editing) {
        await client.put(`/cafe-branches/${editing.id}`, data)
      } else {
        await client.post('/cafe-branches', data)
      }
      close()
      load()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ الفرع')
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">فروعي</h1>
          <p className="mt-1 text-muted">إدارة فروع مقهاك</p>
        </div>
        <button
          onClick={openCreate}
          className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
        >
          + إضافة فرع
        </button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-background text-muted">
            <tr>
              <th className="px-4 py-3 text-start">الاسم</th>
              <th className="px-4 py-3 text-start">المدينة</th>
              <th className="px-4 py-3 text-start">الشارع</th>
              <th className="px-4 py-3 text-start">الموقع</th>
              <th className="px-4 py-3 text-start">الحالة</th>
              <th className="px-4 py-3 text-start">إجراء</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">جاري التحميل...</td></tr>
            ) : branches.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">لا توجد فروع.</td></tr>
            ) : (
              branches.map((b) => (
                <tr key={b.id} className="hover:bg-background/50">
                  <td className="px-4 py-3 font-medium">{b.name}</td>
                  <td className="px-4 py-3">{b.city ?? '-'}</td>
                  <td className="px-4 py-3">{b.street ?? '-'}</td>
                  <td className="px-4 py-3 text-xs text-muted">
                    {b.latitude && b.longitude ? `${Number(b.latitude).toFixed(5)}, ${Number(b.longitude).toFixed(5)}` : '-'}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium text-white ${b.is_active ? 'bg-success' : 'bg-muted'}`}>
                      {b.is_active ? 'نشط' : 'غير نشط'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => openEdit(b)}
                      className="rounded-md border border-border bg-background px-3 py-1 text-xs hover:bg-surface"
                    >
                      تعديل
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {modal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-xl border border-border bg-surface p-6 shadow-lg">
            <h2 className="mb-4 text-lg font-bold">{editing ? 'تعديل فرع' : 'إضافة فرع'}</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">اسم الفرع</label>
                <input
                  required
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full rounded-lg border border-border-strong bg-background px-4 py-2 text-foreground outline-none focus:border-primary"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">المدينة</label>
                <input
                  value={form.city || ''}
                  onChange={(e) => setForm({ ...form, city: e.target.value })}
                  className="w-full rounded-lg border border-border-strong bg-background px-4 py-2 text-foreground outline-none focus:border-primary"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">الشارع</label>
                <input
                  value={form.street || ''}
                  onChange={(e) => setForm({ ...form, street: e.target.value })}
                  className="w-full rounded-lg border border-border-strong bg-background px-4 py-2 text-foreground outline-none focus:border-primary"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">الموقع ومنطقة التوصيل</label>
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    onClick={() => setPickerOpen(true)}
                    className="rounded-lg border border-border bg-background px-4 py-2 text-sm hover:bg-surface"
                  >
                    {form.latitude && form.longitude ? 'تغيير على الخريطة' : 'اختيار على الخريطة'}
                  </button>
                  <span className="text-sm text-muted">
                    {form.latitude && form.longitude
                      ? `${Number(form.latitude).toFixed(5)}, ${Number(form.longitude).toFixed(5)}`
                      : 'لم يُختار موقع'}
                  </span>
                </div>
              </div>
              <label className="flex items-center gap-2 text-sm text-foreground">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                  className="h-4 w-4 rounded border-border-strong"
                />
                نشط
              </label>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={close} className="rounded-lg border border-border bg-background px-4 py-2 text-sm hover:bg-surface">
                  إلغاء
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                >
                  {saving ? 'جاري الحفظ...' : 'حفظ'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

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
