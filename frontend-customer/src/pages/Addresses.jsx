import { useEffect, useState } from 'react'
import client from '../api/client'
import MapPicker from '../components/MapPicker'
import { usePremiumFeatureActive } from '../hooks/usePremiumFeatureActive'

const initial = {
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
  const [addresses, setAddresses] = useState([])
  const [deliveryPrice, setDeliveryPrice] = useState(null)
  const [zones, setZones] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modal, setModal] = useState(false)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [form, setForm] = useState(initial)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  // { title, message } when an address was saved outside the delivery area.
  const [notice, setNotice] = useState(null)
  const branchesFeature = usePremiumFeatureActive('customer_branches')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const [addressesRes, zonesRes] = await Promise.all([
        client.get('/customer/addresses'),
        client.get('/customer/delivery-zones'),
      ])
      setAddresses(addressesRes.data.data.addresses ?? [])
      setDeliveryPrice(addressesRes.data.data.delivery_price ?? null)
      setZones(zonesRes.data.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل العناوين')
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

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.latitude || !form.longitude) {
      setError('حدّد موقع العنوان على الخريطة.')
      return
    }
    setSaving(true)
    try {
      // The server decides the delivery zone from the pin. A pin outside every
      // zone is still saved; the answer says so (202) and we show it as a dialog.
      const data = {
        ...form,
        latitude: Number(form.latitude),
        longitude: Number(form.longitude),
        contact_phones: form.contact_phones.split('\n').map((p) => p.trim()).filter(Boolean),
      }
      if (!data.street) data.street = null
      if (!data.city) data.city = null
      delete data.hex_id

      const res = editing
        ? await client.patch(`/customer/addresses/${editing.id}`, data)
        : await client.post('/customer/addresses', data)
      close()
      load()
      if (res.status === 202 || res.data?.code === 'address_outside_coverage') {
        setNotice({ title: res.data?.title, message: res.data?.message })
      }
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ العنوان')
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      {notice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setNotice(null)}>
          <div role="alertdialog" aria-labelledby="coverage-title" className="w-full max-w-md rounded-2xl border border-border bg-surface p-6 shadow-lg" onClick={(e) => e.stopPropagation()}>
            <h2 id="coverage-title" className="text-lg font-extrabold text-foreground">{notice.title}</h2>
            <p className="mt-3 text-sm leading-relaxed text-muted">{notice.message}</p>
            <button onClick={() => setNotice(null)} className="mt-6 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground">حسناً</button>
          </div>
        </div>
      )}
      <header className="mb-6 flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">عناويني</h1>
          <p className="mt-1 text-muted">إدارة عناوين توصيل مقهاك</p>
          {deliveryPrice != null && (
            <p className="mt-1 text-sm text-primary">سعر التوصيل لموقعك: {Number(deliveryPrice).toFixed(2)} د.ل</p>
          )}
        </div>
        {/* The first address is always allowed; more of them need the branches feature. */}
        {(branchesFeature || (!loading && addresses.length === 0)) && (
          <button
            onClick={openCreate}
            className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
          >
            + إضافة عنوان
          </button>
        )}
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-background text-muted">
            <tr>
              <th className="px-4 py-3 text-start">الاسم</th>
              <th className="px-4 py-3 text-start">المدينة</th>
              <th className="px-4 py-3 text-start">الشارع</th>
              <th className="px-4 py-3 text-start">أرقام التواصل</th>
              <th className="px-4 py-3 text-start">الموقع</th>
              <th className="px-4 py-3 text-start">إجراء</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  <td className="px-4 py-3"><div className="h-4 w-24 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-20 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-28 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-24 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-32 animate-pulse rounded-md bg-border" /></td>
                  <td className="px-4 py-3"><div className="h-4 w-12 animate-pulse rounded-md bg-border" /></td>
                </tr>
              ))
            ) : addresses.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">لا توجد عناوين.</td></tr>
            ) : (
              addresses.map((a) => (
                <tr key={a.id} className="hover:bg-background/50">
                  <td className="px-4 py-3 font-medium">
                    {a.name}
                    {a.is_deliverable === false && <span className="mt-1 block w-fit rounded-full border border-warning/40 bg-warning-soft px-2 py-0.5 text-[11px] font-medium text-foreground">خارج نطاق التوصيل حالياً</span>}
                  </td>
                  <td className="px-4 py-3">{a.city ?? '-'}</td>
                  <td className="px-4 py-3">{a.street ?? '-'}</td>
                  <td className="px-4 py-3">{(a.contact_phones ?? []).join('، ') || '-'}</td>
                  <td className="px-4 py-3 text-xs text-muted">
                    {a.latitude && a.longitude ? `${Number(a.latitude).toFixed(5)}, ${Number(a.longitude).toFixed(5)}` : '-'}
                  </td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => openEdit(a)}
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
            <h2 className="mb-4 text-lg font-bold">{editing ? 'تعديل عنوان' : 'إضافة عنوان'}</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-muted">اسم العنوان</label>
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
                <label className="mb-1.5 block text-sm font-medium text-muted">أرقام التواصل (رقم في كل سطر)</label>
                <textarea
                  rows={3}
                  value={form.contact_phones}
                  onChange={(e) => setForm({ ...form, contact_phones: e.target.value })}
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
