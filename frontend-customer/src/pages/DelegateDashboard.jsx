import { useEffect, useRef, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'

export default function DelegateDashboard() {
  const { user } = useAuth()
  const [isAvailable, setIsAvailable] = useState(user?.delegate_profile?.is_available ?? false)
  const [tracking, setTracking] = useState(false)
  const [location, setLocation] = useState(null)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const watchIdRef = useRef(null)
  const [collect, setCollect] = useState({ mobile_number: '', order_id: '', amount: '', note: '' })
  const [collecting, setCollecting] = useState(false)
  const [collectMessage, setCollectMessage] = useState('')
  const [collections, setCollections] = useState({ total: 0, collections: { data: [] } })

  const [custody, setCustody] = useState(null)

  const loadCollections = () => {
    client.get('/delegate/wallet/collections', { params: { per_page: 5 } }).then((res) => setCollections(res.data)).catch(() => {})
    client.get('/delegate/custody', { params: { per_page: 8 } }).then((res) => setCustody(res.data)).catch(() => {})
  }

  const submitCollect = async (e) => {
    e.preventDefault()
    setCollecting(true)
    setCollectMessage('')
    setError('')
    try {
      const payload = { amount: Number(collect.amount), note: collect.note || null }
      if (collect.order_id) payload.order_id = Number(collect.order_id)
      else payload.mobile_number = collect.mobile_number
      const res = await client.post('/delegate/wallet/collect', payload)
      setCollectMessage(`${res.data?.message ?? 'تم التسجيل'} — ${res.data?.data?.user?.name ?? ''}`)
      setCollect({ mobile_number: '', order_id: '', amount: '', note: '' })
      loadCollections()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تسجيل المبلغ')
    } finally {
      setCollecting(false)
    }
  }

  const sendLocation = async (lat, lng) => {
    try {
      await client.post('/delegate/location', { latitude: lat, longitude: lng })
      setLocation({ lat, lng, at: new Date() })
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إرسال الموقع')
    }
  }

  const startTracking = () => {
    if (!navigator.geolocation) {
      setError('المتصفح لا يدعم تحديد الموقع')
      return
    }

    setTracking(true)
    watchIdRef.current = navigator.geolocation.watchPosition(
      (pos) => {
        const { latitude, longitude } = pos.coords
        sendLocation(latitude, longitude)
      },
      (err) => {
        setError('تعذر الحصول على الموقع: ' + err.message)
        setTracking(false)
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 10000 }
    )
  }

  const stopTracking = () => {
    if (watchIdRef.current) {
      navigator.geolocation.clearWatch(watchIdRef.current)
      watchIdRef.current = null
    }
    setTracking(false)
  }

  const toggleAvailability = async () => {
    setSaving(true)
    try {
      const next = !isAvailable
      await client.post('/delegate/availability', { is_available: next })
      setIsAvailable(next)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الحالة')
    } finally {
      setSaving(false)
    }
  }

  useEffect(() => {
    startTracking()
    loadCollections()
    return () => stopTracking()
  }, [])

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">لوحة المندوب</h1>
        <p className="mt-1 text-muted">إدارة توفرك وموقعك المباشر</p>
      </header>

      {error && (
        <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {custody && (
        <div className={`mb-4 rounded-2xl p-5 shadow-sm ${Number(custody.balance) > 0 ? 'bg-amber-500 text-white' : 'bg-primary text-primary-foreground'}`}>
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <div className="text-sm opacity-90">العهدة النقدية (مستحقة للمكتب)</div>
              <div className="mt-1 text-4xl font-black">{Number(custody.balance).toFixed(2)} <span className="text-lg">د.ل</span></div>
              <div className="mt-1 text-sm opacity-90">
                منذ آخر تسكير: {custody.since_last_settlement.collections} عملية بقيمة {Number(custody.since_last_settlement.amount).toFixed(2)} د.ل
              </div>
            </div>
            <div className="text-sm opacity-90">
              {custody.last_settlement
                ? <>آخر تسليم: {Number(custody.last_settlement.amount).toFixed(2)} د.ل<br />{new Date(custody.last_settlement.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</>
                : 'لم يتم أي تسليم بعد'}
            </div>
          </div>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="text-sm text-muted">الحالة</div>
          <div className="mt-3 flex items-center justify-between">
            <span className={`font-semibold ${isAvailable ? 'text-green-600' : 'text-gray-500'}`}>
              {isAvailable ? 'متاح للتوصيل' : 'غير متاح'}
            </span>
            <button
              onClick={toggleAvailability}
              disabled={saving}
              className={`rounded-lg px-4 py-2 text-sm font-medium text-white transition ${
                isAvailable ? 'bg-amber-500 hover:bg-amber-600' : 'bg-green-600 hover:bg-green-700'
              }`}
            >
              {saving ? 'جاري...' : isAvailable ? 'إيقاف التوفر' : 'تفعيل التوفر'}
            </button>
          </div>
        </div>

        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="text-sm text-muted">تتبع الموقع</div>
          <div className="mt-3 flex items-center justify-between">
            <span className={`font-semibold ${tracking ? 'text-green-600' : 'text-gray-500'}`}>
              {tracking ? 'مفعّل' : 'متوقف'}
            </span>
            <button
              onClick={tracking ? stopTracking : startTracking}
              className={`rounded-lg px-4 py-2 text-sm font-medium text-white transition ${
                tracking ? 'bg-red-500 hover:bg-red-600' : 'bg-primary hover:bg-primary/90'
              }`}
            >
              {tracking ? 'إيقاف التتبع' : 'تفعيل التتبع'}
            </button>
          </div>
        </div>
      </div>

      <div className="mt-4 rounded-xl border border-border bg-surface p-5 shadow-sm">
        <div className="text-sm text-muted">الموقع الحالي</div>
        <div className="mt-2 text-foreground">
          {location ? (
            <div className="space-y-1 text-sm">
              <div>خط العرض: {location.lat.toFixed(6)}</div>
              <div>خط الطول: {location.lng.toFixed(6)}</div>
              <div className="text-xs text-muted">آخر تحديث: {location.at.toLocaleString('ar-LY')}</div>
            </div>
          ) : (
            <span className="text-sm text-muted">في انتظار الموقع...</span>
          )}
        </div>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <form onSubmit={submitCollect} className="space-y-3 rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="font-semibold text-foreground">تحصيل نقدي لمحفظة زبون</div>
          <p className="text-xs text-muted">المبلغ يُضاف لمحفظة الزبون فوراً ويُسجَّل باسمك للتسوية مع الإدارة.</p>
          <div className="grid gap-3 sm:grid-cols-2">
            <input className="rounded-lg border border-border-strong bg-background px-3 py-2 text-sm" placeholder="رقم الطلب (اختياري)" value={collect.order_id} onChange={(e) => setCollect({ ...collect, order_id: e.target.value })} />
            <input className="rounded-lg border border-border-strong bg-background px-3 py-2 text-sm" placeholder="أو جوال الزبون" disabled={!!collect.order_id} value={collect.mobile_number} onChange={(e) => setCollect({ ...collect, mobile_number: e.target.value })} />
          </div>
          <input type="number" min="1" step="0.01" required className="w-full rounded-lg border border-border-strong bg-background px-3 py-2 text-sm" placeholder="المبلغ المستلم (د.ل)" value={collect.amount} onChange={(e) => setCollect({ ...collect, amount: e.target.value })} />
          <input className="w-full rounded-lg border border-border-strong bg-background px-3 py-2 text-sm" placeholder="ملاحظة (اختياري)" value={collect.note} onChange={(e) => setCollect({ ...collect, note: e.target.value })} />
          {collectMessage && <div className="rounded-md bg-success-soft px-3 py-2 text-sm text-success">{collectMessage}</div>}
          <button type="submit" disabled={collecting || (!collect.order_id && !collect.mobile_number)} className="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60">
            {collecting ? 'جاري...' : 'تسجيل المبلغ'}
          </button>
        </form>

        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <div className="font-semibold text-foreground">سجل العهدة</div>
          <ul className="mt-3 divide-y divide-border text-sm">
            {(custody?.entries?.data ?? []).length === 0 && <li className="py-3 text-muted">لا توجد حركات.</li>}
            {(custody?.entries?.data ?? []).map((e) => (
              <li key={e.id} className="flex items-center justify-between gap-3 py-2">
                <div>
                  <div className="text-foreground">{e.note}</div>
                  <div className="text-xs text-muted">{new Date(e.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</div>
                </div>
                <span className={`font-bold ${Number(e.amount) < 0 ? 'text-green-700' : 'text-amber-600'}`}><bdi>{Number(e.amount) > 0 ? '+' : ''}{Number(e.amount).toFixed(2)}</bdi></span>
              </li>
            ))}
          </ul>
          <div className="mt-2 text-xs text-muted">إجمالي ما حصّلته للمحافظ: {Number(collections.total || 0).toFixed(2)} د.ل</div>
        </div>
      </div>
    </>
  )
}
