import { useEffect, useRef, useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApiResource } from '../hooks/useApiResource'
import echo from '../echo'
import client from '../api/client'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Modal from '../components/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { latLngToCell, cellToBoundary, cellToLatLng, polygonToCells } from 'h3-js'

const defaultIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

const delegateIcon = L.divIcon({
  className: 'custom-div-icon',
  html: `<div style="background:#f59e0b;width:14px;height:14px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
  iconSize: [14, 14],
  iconAnchor: [7, 7],
})

const delegateOfflineIcon = L.divIcon({
  className: 'custom-div-icon',
  html: `<div style="background:#9ca3af;width:14px;height:14px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
  iconSize: [14, 14],
  iconAnchor: [7, 7],
})

const H3_RESOLUTION = 4

// Rough polygon around Libya to reduce sea cells
const LIBYA_POLYGON = [
  [33.0, 11.5],
  [32.7, 12.0],
  [32.9, 13.0],
  [32.8, 15.0],
  [33.0, 17.0],
  [32.9, 19.0],
  [33.1, 21.0],
  [32.8, 23.0],
  [31.8, 25.0],
  [29.5, 25.0],
  [24.0, 25.0],
  [21.5, 24.5],
  [20.0, 23.5],
  [19.5, 21.0],
  [20.0, 15.0],
  [24.0, 10.0],
  [28.0, 9.5],
  [31.0, 10.0],
]

export default function Map() {
  const navigate = useNavigate()
  const { items: warehouses, loading: whLoading } = useApiResource('/warehouses')
  const { items: branches, loading: branchLoading } = useApiResource('/cafe-branches')
  const { items: delegates, loading: delegateLoading } = useApiResource('/delegates')
  const { items: zones, loading: zoneLoading, fetch } = useApiResource('/delivery-zones?per_page=10000')
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)
  const clickHandlerRef = useRef(null)
  const [layer, setLayer] = useState('all')
  const [gridVisible, setGridVisible] = useState(true)
  const [showPricedOnly, setShowPricedOnly] = useState(false)
  const [modal, setModal] = useState(false)
  const [pendingCell, setPendingCell] = useState(null)
  const [editingZone, setEditingZone] = useState(null)
  const [form, setForm] = useState({ hex_id: '', name: '', delivery_price: '', is_active: true })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [liveDelegates, setLiveDelegates] = useState([])

  const zoneMap = useMemo(() => {
    const map = {}
    zones.forEach((z) => { map[z.hex_id] = z })
    return map
  }, [zones])

  const libyaCells = useMemo(() => {
    try {
      return polygonToCells(LIBYA_POLYGON, H3_RESOLUTION)
    } catch {
      return []
    }
  }, [])

  useEffect(() => {
    if (mapInstanceRef.current) return

    const map = L.map(mapRef.current).setView([27.0, 17.0], 6)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    mapInstanceRef.current = map

    echo.channel('delegates.locations')
      .listen('.delegate.location.updated', (e) => {
        setLiveDelegates((prev) => {
          const filtered = prev.filter((d) => d.id !== e.id)
          return [...filtered, {
            id: e.id,
            name: e.name,
            latitude: e.latitude,
            longitude: e.longitude,
            is_available: e.is_available,
            location_updated_at: e.location_updated_at,
          }]
        })
      })

    return () => {
      echo.leaveChannel('delegates.locations')
    }
  }, [])

  useEffect(() => {
    setLiveDelegates(delegates)
  }, [delegates])

  useEffect(() => {
    const map = mapInstanceRef.current
    if (!map) return

    if (clickHandlerRef.current) {
      map.off('click', clickHandlerRef.current)
    }

    const handler = (e) => {
      const cell = latLngToCell(e.latlng.lat, e.latlng.lng, H3_RESOLUTION)
      const existing = zoneMap[cell]
      const center = cellToLatLng(cell)
      const boundary = cellToBoundary(cell)

      setPendingCell({ index: cell, center: { lat: center[0], lng: center[1] }, boundary })
      setEditingZone(existing || null)
      setForm(existing
        ? { hex_id: existing.hex_id, name: existing.name || '', delivery_price: String(existing.delivery_price), is_active: existing.is_active }
        : { hex_id: cell, name: '', delivery_price: '', is_active: true })
      setError('')
      setModal(true)
    }

    map.on('click', handler)
    clickHandlerRef.current = handler

    return () => {
      if (clickHandlerRef.current) map.off('click', clickHandlerRef.current)
    }
  }, [zoneMap])

  useEffect(() => {
    const map = mapInstanceRef.current
    if (!map) return

    map.eachLayer((l) => {
      if (l instanceof L.Marker || l instanceof L.Circle || l instanceof L.Polygon) map.removeLayer(l)
    })

    if (layer === 'warehouses' || layer === 'all') {
      warehouses.forEach((w) => {
        if (w.latitude != null && w.longitude != null) {
          L.marker([w.latitude, w.longitude], { icon: defaultIcon })
            .addTo(map)
            .bindPopup(`<b>${w.name}</b><br/>${w.city ?? ''}`)
        }
      })
    }

    if (layer === 'branches' || layer === 'all') {
      branches.forEach((b) => {
        if (b.latitude != null && b.longitude != null) {
          L.circle([b.latitude, b.longitude], { radius: 500, color: '#2563eb' })
            .addTo(map)
            .bindPopup(`<b>${b.name}</b><br/>${b.city ?? ''}`)
        }
      })
    }

    if (layer === 'delegates' || layer === 'all') {
      liveDelegates.forEach((d) => {
        if (d.latitude != null && d.longitude != null) {
          const updatedAt = d.location_updated_at
            ? new Date(d.location_updated_at).toLocaleString('ar-LY')
            : '-'
          const popup = `<b>${d.name}</b><br/>${d.is_available ? 'متاح' : 'غير مت'}<br/>آخر تحديث: ${updatedAt}`
          L.marker([d.latitude, d.longitude], { icon: d.is_available ? delegateIcon : delegateOfflineIcon })
            .addTo(map)
            .bindPopup(popup)
        }
      })
    }

    if ((layer === 'zones' || layer === 'all') && gridVisible) {
      libyaCells.forEach((cell) => {
        const zone = zoneMap[cell]
        if (showPricedOnly && !zone) return

        try {
          const boundary = cellToBoundary(cell)
          const center = cellToLatLng(cell)
          const isPriced = Boolean(zone)
          const polygon = L.polygon(boundary, {
            color: isPriced ? '#15803d' : '#d1d5db',
            fillColor: isPriced ? '#22c55e' : '#f9fafb',
            fillOpacity: isPriced ? 0.6 : 0.08,
            weight: isPriced ? 3 : 0.8,
          }).addTo(map)

          polygon.on('click', (e) => {
            L.DomEvent.stopPropagation(e)
            if (zone) {
              navigate(`/delivery-zones/${zone.id}`)
              return
            }
            setPendingCell({ index: cell, center: { lat: center[0], lng: center[1] }, boundary })
            setEditingZone(null)
            setForm({ hex_id: cell, name: '', delivery_price: '', is_active: true })
            setError('')
            setModal(true)
          })

          polygon.bindTooltip(isPriced
            ? `${zone.name || 'منطقة مسعّرة'} — ${Number(zone.delivery_price).toFixed(2)} د.ل`
            : `${cell} — انقر لتحديد السعر`, { direction: 'top', sticky: true })
        } catch {
          // ignore invalid cells
        }
      })
    }
  }, [warehouses, branches, liveDelegates, zones, layer, gridVisible, showPricedOnly, libyaCells, zoneMap])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!pendingCell) return
    setSaving(true)
    setError('')
    try {
      const payload = {
        hex_id: pendingCell.index,
        name: form.name,
        delivery_price: Number(form.delivery_price),
        latitude: pendingCell.center.lat,
        longitude: pendingCell.center.lng,
        is_active: Boolean(form.is_active),
      }
      if (editingZone) {
        await client.put(`/delivery-zones/${editingZone.id}`, payload)
      } else {
        await client.post('/delivery-zones', payload)
      }
      setModal(false)
      setEditingZone(null)
      fetch()
      const map = mapInstanceRef.current
      if (map) map.flyTo([pendingCell.center.lat, pendingCell.center.lng], 10)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ المنطقة')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!editingZone) return
    setSaving(true)
    try {
      await client.delete(`/delivery-zones/${editingZone.id}`)
      setModal(false)
      setEditingZone(null)
      fetch()
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف المنطقة')
    } finally {
      setSaving(false)
    }
  }

  const loading = whLoading || branchLoading || delegateLoading || zoneLoading

  const closeModal = () => {
    setModal(false)
    setEditingZone(null)
  }

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الخريطة</h1>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant={layer === 'warehouses' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('warehouses')}>المستودعات</Button>
          <Button variant={layer === 'branches' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('branches')}>الفروع</Button>
          <Button variant={layer === 'delegates' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('delegates')}>المناديب</Button>
          <Button variant={layer === 'zones' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('zones')}>المناطق</Button>
          <Button variant={layer === 'all' ? 'primary' : 'secondary'} size="sm" onClick={() => setLayer('all')}>الكل</Button>
          <span className="hidden h-6 w-px bg-border sm:inline-block" />
          <Button variant={gridVisible ? 'primary' : 'secondary'} size="sm" onClick={() => setGridVisible((v) => !v)}>
            {gridVisible ? 'إخفاء الشبكة' : 'إظهار الشبكة'}
          </Button>
          <Button variant={showPricedOnly ? 'primary' : 'secondary'} size="sm" onClick={() => setShowPricedOnly((v) => !v)}>
            {showPricedOnly ? 'المسعّرة فقط' : 'كل الخلايا'}
          </Button>
        </div>
      </header>
      <div className="mb-4 flex flex-wrap items-center gap-6 text-sm text-muted">
        <div className="flex items-center gap-2">
          <span className="inline-block h-3 w-3 rounded-full border-2 border-white bg-amber-500 shadow" />
          مندوب متاح
        </div>
        <div className="flex items-center gap-2">
          <span className="inline-block h-3 w-3 rounded-full border-2 border-white bg-gray-400 shadow" />
          مندوب غير متاح
        </div>
        <div className="flex items-center gap-2">
          <span className="inline-block h-4 w-4 rounded-sm border-2 border-green-700 bg-green-500" />
          منطقة مسعّرة
        </div>
        <div className="flex items-center gap-2">
          <span className="inline-block h-4 w-4 rounded-sm border border-border bg-surface" />
          بلا سعر
        </div>
      </div>
      <div className="mb-4 rounded-lg border border-primary/20 bg-primary-soft px-4 py-3 text-sm text-primary">
        انقر أي خلية سداسية في ليبيا لتحديد أو تعديل سعر التوصيل.
      </div>
      {loading && <div className="mb-4 text-sm text-muted">جاري تحميل بيانات الخريطة...</div>}
      <div ref={mapRef} className="h-[600px] rounded-lg border border-border" />

      <Modal title={editingZone ? 'تعديل منطقة توصيل' : 'منطقة توصيل جديدة'} open={modal} onClose={closeModal}>
        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

          <Input
            label="الاسم"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
          />
          <Input
            label="سعر التوصيل"
            type="number"
            step="0.01"
            min="0"
            value={form.delivery_price}
            onChange={(e) => setForm({ ...form, delivery_price: e.target.value })}
            required
          />
          <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
              checked={form.is_active}
            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            />
            نشط
          </label>
          <div className="text-sm text-muted">
            المركز: {pendingCell?.center.lat.toFixed(5)}, {pendingCell?.center.lng.toFixed(5)}
          </div>
          <div className="flex items-center justify-end gap-2 mt-6">
            {editingZone && (
              <Button type="button" variant="danger" onClick={handleDelete} disabled={saving}>حذف</Button>
            )}
            <Button type="button" variant="secondary" onClick={closeModal}>إلغاء</Button>
            <Button type="submit" variant="primary" disabled={saving}>{saving ? 'جاري الحفظ...' : 'حفظ المنطقة'}</Button>
          </div>
        </form>
      </Modal>
    </>
  )
}
