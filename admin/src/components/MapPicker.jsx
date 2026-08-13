import { useEffect, useRef, useState, useMemo } from 'react'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { latLngToCell, cellToBoundary, polygonToCells } from 'h3-js'
import Modal from './Modal'
import Button from './ui/Button'
import Input from './ui/Input'

const defaultIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
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

export default function MapPicker({ open, onClose, onSelect, initial, zones = [] }) {
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)
  const markerRef = useRef(null)
  const gridLayerRef = useRef(null)
  const highlightRef = useRef(null)
  const [selected, setSelected] = useState(initial || null)
  const [manualLat, setManualLat] = useState(initial ? String(initial.lat) : '')
  const [manualLng, setManualLng] = useState(initial ? String(initial.lng) : '')

  const zoneMap = useMemo(() => {
    const map = {}
    zones.forEach((z) => { map[z.hex_id] = z })
    return map
  }, [zones])

  const libyaCells = (() => {
    try {
      return polygonToCells(LIBYA_POLYGON, H3_RESOLUTION)
    } catch {
      return []
    }
  })()

  const selectLocation = (lat, lng) => {
    try {
      const hexId = latLngToCell(lat, lng, H3_RESOLUTION)
      setSelected({ lat, lng, hexId })
      setManualLat(String(lat.toFixed(5)))
      setManualLng(String(lng.toFixed(5)))

      const map = mapInstanceRef.current
      if (!map) return

      if (markerRef.current) {
        markerRef.current.setLatLng([lat, lng])
      } else {
        markerRef.current = L.marker([lat, lng], { icon: defaultIcon }).addTo(map)
      }

      const boundary = cellToBoundary(hexId)
      if (highlightRef.current) {
        highlightRef.current.setLatLngs(boundary)
      } else {
        highlightRef.current = L.polygon(boundary, {
          color: '#0f766e',
          fillColor: '#0f766e',
          fillOpacity: 0.35,
          weight: 3,
        }).addTo(map)
      }
    } catch {
      // ignore invalid coordinates
    }
  }

  useEffect(() => {
    if (!open || mapInstanceRef.current) return

    const center = initial ? [initial.lat, initial.lng] : [27.0, 17.0]
    const map = L.map(mapRef.current).setView(center, 6)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)

    mapInstanceRef.current = map

    // Draw the H3 grid
    gridLayerRef.current = L.layerGroup().addTo(map)
    libyaCells.forEach((cell) => {
      try {
        const zone = zoneMap[cell]
        const boundary = cellToBoundary(cell)
        const polygon = L.polygon(boundary, {
          color: zone ? '#15803d' : '#d6d3d1',
          fillColor: zone ? '#22c55e' : '#f5f5f4',
          fillOpacity: zone ? 0.45 : 0.08,
          weight: zone ? 2 : 0.8,
        }).addTo(gridLayerRef.current)

        if (zone) {
          const tooltipText = zone.delivery_price != null
            ? `${zone.name || 'منطقة مسعّرة'} — ${Number(zone.delivery_price).toFixed(2)} د.ل`
            : `${zone.name || 'منطقة بلا سعر'}`
          polygon.bindTooltip(tooltipText, { direction: 'top', sticky: true })
        }

        polygon.on('click', (e) => {
          L.DomEvent.stopPropagation(e)
          const center = e.latlng
          selectLocation(center.lat, center.lng)
        })
      } catch {
        // ignore invalid cells
      }
    })

    map.on('click', (e) => {
      selectLocation(e.latlng.lat, e.latlng.lng)
    })

    if (initial) {
      selectLocation(initial.lat, initial.lng)
    }
  }, [open, initial, zoneMap, libyaCells])

  useEffect(() => {
    if (!open) {
      setSelected(initial || null)
      setManualLat(initial ? String(initial.lat) : '')
      setManualLng(initial ? String(initial.lng) : '')
    }
  }, [open, initial])

  const applyManualCoordinates = () => {
    const lat = Number(manualLat)
    const lng = Number(manualLng)
    if (!isNaN(lat) && !isNaN(lng)) {
      selectLocation(lat, lng)
      const map = mapInstanceRef.current
      if (map) map.flyTo([lat, lng], 10)
    }
  }

  const handleConfirm = () => {
    if (selected) {
      onSelect(selected)
      onClose()
    }
  }

  return (
    <Modal title="اختيار موقع الفرع ومنطقة التوصيل" open={open} onClose={onClose}>
      <div
        ref={mapRef}
        className="mb-4 h-[400px] rounded-lg border border-border"
      />

      <div className="mb-4 flex flex-wrap items-center gap-4 text-sm text-muted">
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
        انقر أي خلية لاختيار موقع الفرع ومنطقة التوصيل. مرّر الماوس فوق الخلايا الخضراء لرؤية السعر.
      </div>

      <div className="mb-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
        <Input
          label="خط العرض"
          type="number"
          step="any"
          value={manualLat}
          onChange={(e) => setManualLat(e.target.value)}
          placeholder="مثال: 27.00000"
        />
        <Input
          label="خط الطول"
          type="number"
          step="any"
          value={manualLng}
          onChange={(e) => setManualLng(e.target.value)}
          placeholder="مثال: 17.00000"
        />
        <div className="flex items-end">
          <Button type="button" variant="secondary" onClick={applyManualCoordinates}>
            تحديد
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="text-sm text-muted">
          {selected ? (
            <div className="space-y-0.5">
              <div>
                الموقع: {selected.lat.toFixed(5)}, {selected.lng.toFixed(5)}
              </div>

            </div>
          ) : (
            'اختر خلية من الخريطة أو أدخل الإحداثيات يدويًا.'
          )}
        </div>
        <div className="flex items-center justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            إلغاء
          </Button>
          <Button
            type="button"
            variant="primary"
            onClick={handleConfirm}
            disabled={!selected}
          >
            تأكيد
          </Button>
        </div>
      </div>
    </Modal>
  )
}
