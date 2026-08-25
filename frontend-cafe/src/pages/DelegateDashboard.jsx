import { useEffect, useRef, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'

export default function DelegateDashboard() {
  const { user } = useAuth()
  const [isAvailable, setIsAvailable] = useState(user?.is_available ?? false)
  const [tracking, setTracking] = useState(false)
  const [location, setLocation] = useState(null)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const watchIdRef = useRef(null)

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
    </>
  )
}
