import { useEffect, useRef, useState, useMemo } from 'react'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { cellToBoundary, cellToLatLng, polygonToCells } from 'h3-js'
import Modal from './Modal'
import Button from './ui/Button'

const H3_RESOLUTION = 4

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

const selectedStyle = {
  color: '#2563eb',
  fillColor: '#3b82f6',
  fillOpacity: 0.45,
  weight: 3,
}

const pricedStyle = {
  color: '#15803d',
  fillColor: '#22c55e',
  fillOpacity: 0.5,
  weight: 2,
}

const emptyStyle = {
  color: '#d1d5db',
  fillColor: '#f9fafb',
  fillOpacity: 0.08,
  weight: 0.8,
}

export default function WarehouseZonePicker({ open, onClose, onSave, initialHexIds = [], zones = [] }) {
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)
  const gridLayerRef = useRef(null)
  const polygonMapRef = useRef(new Map())
  const [selected, setSelected] = useState(new Set())
  const [saving, setSaving] = useState(false)
  const [count, setCount] = useState(0)
  const appliedInitialRef = useRef(false)

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

  const applyStyle = (cell, isSelected) => {
    const polygon = polygonMapRef.current.get(cell)
    if (!polygon) return
    const zone = zoneMap[cell]
    const style = isSelected ? selectedStyle : zone ? pricedStyle : emptyStyle
    polygon.setStyle(style)
    polygon.setTooltipContent(
      isSelected
        ? 'منطقة المستودع'
        : zone
          ? `${zone.name || 'منطقة مسعّرة'} — ${Number(zone.delivery_price).toFixed(2)} د.ل`
          : `${cell} — انقر لإضافتها للمستودع`
    )
  }

  useEffect(() => {
    if (!open) return

    let map = mapInstanceRef.current
    if (!map) {
      map = L.map(mapRef.current).setView([27.0, 17.0], 6)
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map)
      mapInstanceRef.current = map
    }

    if (!gridLayerRef.current) {
      gridLayerRef.current = L.layerGroup().addTo(map)
    }

    const currentPolygons = polygonMapRef.current

    libyaCells.forEach((cell) => {
      if (currentPolygons.has(cell)) return
      try {
        const zone = zoneMap[cell]
        const boundary = cellToBoundary(cell)
        const polygon = L.polygon(boundary, zone ? pricedStyle : emptyStyle).addTo(gridLayerRef.current)
        polygon.bindTooltip(
          zone
            ? `${zone.name || 'منطقة مسعّرة'} — ${Number(zone.delivery_price).toFixed(2)} د.ل`
            : `${cell} — انقر لإضافتها للمستودع`,
          { direction: 'top', sticky: true }
        )
        polygon.on('click', (e) => {
          L.DomEvent.stopPropagation(e)
          setSelected((prev) => {
            const next = new Set(prev)
            if (next.has(cell)) next.delete(cell)
            else next.add(cell)
            return next
          })
        })
        currentPolygons.set(cell, polygon)
      } catch {
        // ignore invalid cells
      }
    })

    if (!appliedInitialRef.current) {
      const initial = new Set(initialHexIds)
      setSelected(initial)
      setCount(initial.size)
      initialHexIds.forEach((cell) => applyStyle(cell, true))
      appliedInitialRef.current = true
    }
  }, [open, libyaCells, zoneMap, initialHexIds])

  useEffect(() => {
    if (!open) {
      appliedInitialRef.current = false
      return
    }
    // ponytail: O(n) style update on selection change; n is small enough for Libya grid
    polygonMapRef.current.forEach((_, cell) => applyStyle(cell, selected.has(cell)))
    setCount(selected.size)
  }, [open, selected, zoneMap])

  const handleSave = async () => {
    setSaving(true)
    try {
      await onSave(Array.from(selected))
      onClose()
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal title="تحديد نطاق المستودع" open={open} onClose={onClose}>
      <div className="mb-4 flex flex-wrap items-center gap-4 text-sm text-muted">
        <div className="flex items-center gap-2">
          <span className="inline-block h-4 w-4 rounded-sm border-2 border-blue-600 bg-blue-500" />
          منطقة المستودع ({count})
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
        انقر أي خلية سداسية لإضافتها أو إزالتها من نطاق المستودع. المستودع يقدر يغطي عشرات الخلايا.
      </div>
      <div ref={mapRef} className="mb-4 h-[500px] rounded-lg border border-border" />
      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="secondary" onClick={onClose}>إلغاء</Button>
        <Button type="button" variant="primary" onClick={handleSave} disabled={saving}>
          {saving ? 'جاري الحفظ...' : 'حفظ النطاق'}
        </Button>
      </div>
    </Modal>
  )
}
