import { useEffect, useState } from 'react'
import client from '../api/client'
import Button from '../components/ui/Button'
import { PageSkeleton } from '../components/ui/Skeleton'

export default function PremiumFeatures() {
  const [features, setFeatures] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(null)

  const fetchFeatures = async () => {
    setLoading(true)
    try {
      const res = await client.get('/premium-features')
      const items = res.data?.data ?? res.data ?? []
      setFeatures(items)
      try {
        localStorage.setItem('premium-features-cache', JSON.stringify(items))
      } catch {}
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الميزات')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchFeatures()
  }, [])

  const toggle = async (feature) => {
    setSaving(feature.id)
    try {
      await client.patch(`/premium-features/${feature.id}`, { is_active: !feature.is_active })
      const updated = features.map((f) => (f.id === feature.id ? { ...f, is_active: !f.is_active } : f))
      setFeatures(updated)
      // keep the flicker-cache in sync so other pages/tabs render the new state instantly
      try {
        localStorage.setItem('premium-features-cache', JSON.stringify(updated))
      } catch {}
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الميزة')
    } finally {
      setSaving(null)
    }
  }

  if (loading) return <PageSkeleton />

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الميزات المميزة</h1>
      </header>

      {error && <div className="mt-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mt-4 rounded-lg border border-border bg-white shadow-sm">
        {features.length === 0 ? (
          <div className="p-8 text-center text-muted">لا توجد ميزات</div>
        ) : (
          <div className="divide-y divide-border">
            {features.map((f) => (
              <div key={f.id} className="flex items-center justify-between p-4">
                <div>
                  <div className="font-semibold text-foreground">{f.name}</div>
                  <div className="text-xs text-muted">{f.code}</div>
                </div>
                <Button
                  variant={f.is_active ? 'primary' : 'secondary'}
                  size="sm"
                  onClick={() => toggle(f)}
                  disabled={saving === f.id}
                >
                  {saving === f.id ? 'جاري الحفظ...' : f.is_active ? 'مفعلة' : 'معطلة'}
                </Button>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  )
}
