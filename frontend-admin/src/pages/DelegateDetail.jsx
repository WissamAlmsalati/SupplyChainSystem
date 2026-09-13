import { useEffect, useRef, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import echo from '../echo'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { StatusBadge } from '../lib/status'
import { PageSkeleton } from '../components/ui/Skeleton'

const delegateIcon = L.divIcon({
  className: 'custom-div-icon',
  html: `<div style="background:#f59e0b;width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.4);"></div>`,
  iconSize: [16, 16],
  iconAnchor: [8, 8],
})

const delegateOfflineIcon = L.divIcon({
  className: 'custom-div-icon',
  html: `<div style="background:#9ca3af;width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.4);"></div>`,
  iconSize: [16, 16],
  iconAnchor: [8, 8],
})

function formatMoney(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function DelegateDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [delegate, setDelegate] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)
  const markerRef = useRef(null)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/delegates/${id}`)
      const data = res.data?.data ?? res.data
      const profile = data?.delegate_profile ?? {}
      // Location fields live on delegate_profile; flatten to match the live broadcast shape.
      setDelegate({
        ...data,
        latitude: profile.latitude,
        longitude: profile.longitude,
        is_available: profile.is_available,
        location_updated_at: profile.location_updated_at,
      })
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات المندوب')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  useEffect(() => {
    if (loading || !delegate || mapInstanceRef.current || !mapRef.current) return

    const map = L.map(mapRef.current).setView([27.0, 17.0], 6)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    mapInstanceRef.current = map

    echo.channel('delegates.locations')
      .listen('.delegate.location.updated', (e) => {
        if (String(e.id) === String(id)) {
          setDelegate((prev) =>
            prev
              ? {
                  ...prev,
                  latitude: e.latitude,
                  longitude: e.longitude,
                  is_available: e.is_available,
                  location_updated_at: e.location_updated_at,
                }
              : prev
          )
        }
      })

    return () => {
      echo.leaveChannel('delegates.locations')
    }
  }, [loading, delegate, id])

  useEffect(() => {
    const map = mapInstanceRef.current
    if (!map || !delegate) return

    const lat = Number(delegate.latitude)
    const lng = Number(delegate.longitude)
    const hasCoords = !isNaN(lat) && !isNaN(lng)

    if (markerRef.current) {
      map.removeLayer(markerRef.current)
      markerRef.current = null
    }

    if (hasCoords) {
      const icon = delegate.is_available ? delegateIcon : delegateOfflineIcon
      const marker = L.marker([lat, lng], { icon })
        .addTo(map)
        .bindPopup(
          `<b>${delegate.name}</b><br/>${delegate.is_available ? 'متاح' : 'غير متاح'}<br/>${delegate.location_updated_at ? new Date(delegate.location_updated_at).toLocaleString('en-US') : ''}`
        )
        .openPopup()
      markerRef.current = marker
      map.setView([lat, lng], 14)
    } else {
      map.setView([27.0, 17.0], 6)
    }
  }, [delegate])

  const orderColumns = [
    { key: 'order_number', label: 'رقم الطلب', render: (r) => r.order_number ?? `#${r.id}` },
    { key: 'id', label: '#' },
    { key: 'address', label: 'العنوان', render: (r) => r.delivery_address_name ?? '-' },
    {
      key: 'status',
      label: 'الحالة',
      render: (r) => <StatusBadge status={r.status} />,
    },
    { key: 'total_amount', label: 'الإجمالي', render: (r) => `${formatMoney(r.total_amount)} د.ل` },
  ]

  if (loading) return <PageSkeleton />
  if (!delegate) return <div className="text-danger">{error || 'المندوب غير موجود.'}</div>

  const orders = delegate.delegated_orders ?? delegate.delegatedOrders ?? []

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل المندوب</h1>
          <p className="mt-1 text-muted">{delegate.name || 'مندوب'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/delegates')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات المندوب</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {delegate.name ?? '-'}</div>
              <div><span className="font-medium">البريد:</span> {delegate.email ?? '-'}</div>
              <div><span className="font-medium">الجوال:</span> {delegate.mobile_number ?? '-'}</div>
              <div>
                <span className="font-medium">الحالة:</span>{' '}
                <Badge variant={delegate.is_active ? 'success' : 'default'}>{delegate.is_active ? 'نشط' : 'غير نشط'}</Badge>
              </div>
              <div>
                <span className="font-medium">متاح:</span>{' '}
                <Badge variant={delegate.is_available ? 'success' : 'default'}>{delegate.is_available ? 'نعم' : 'لا'}</Badge>
              </div>
              <div>
                <span className="font-medium">آخر تحديث للموقع:</span>{' '}
                {delegate.location_updated_at ? new Date(delegate.location_updated_at).toLocaleString('en-US') : '-'}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ملخص الطلبات</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">عدد الطلبات:</span> {orders.length}</div>
              <div>
                <span className="font-medium">إجمالي قيمة الطلبات:</span>{' '}
                {formatMoney(orders.reduce((sum, o) => sum + (Number(o.total_amount) || 0), 0))} د.ل
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>الموقع المباشر</CardTitle>
        </CardHeader>
        <CardContent>
          <div ref={mapRef} className="h-80 w-full rounded-xl border border-border" />
          {!delegate.latitude && !delegate.longitude && (
            <div className="mt-3 text-sm text-muted">لا يوجد موقع مسجل لهذا المندوب.</div>
          )}
        </CardContent>
      </Card>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>الطلبات المسندة</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={orderColumns}
            rows={orders}
            loading={false}
            emptyText="لا توجد طلبات مسندة لهذا المندوب."
          />
        </CardContent>
      </Card>
    </>
  )
}
