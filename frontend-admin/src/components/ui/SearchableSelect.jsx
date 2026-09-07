import { useEffect, useRef, useState } from 'react'

export default function SearchableSelect({
  label,
  placeholder = 'اختر...',
  searchPlaceholder = 'ابحث...',
  options = [],
  value,
  onChange,
  getLabel = (o) => String(o),
  getValue = (o) => o?.id ?? o,
  required = false,
  onQueryChange = null,
  loading = false,
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const ref = useRef(null)

  const selected = options.find((o) => String(getValue(o)) === String(value))
  const filtered = options.filter((o) =>
    getLabel(o).toLowerCase().includes(query.toLowerCase())
  )

  useEffect(() => {
    function handleClickOutside(e) {
      if (ref.current && !ref.current.contains(e.target)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    if (open) setQuery('')
  }, [open])

  return (
    <div className="w-full" ref={ref}>
      {label && (
        <label className="mb-1.5 block text-sm font-medium text-muted">
          {label}
          {required && <span className="text-danger"> *</span>}
        </label>
      )}
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className={`flex w-full items-center justify-between rounded-md border bg-surface px-3.5 py-2 text-sm text-foreground shadow-sm transition-colors focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none ${
          open ? 'border-primary ring-4 ring-primary/10' : 'border-border-strong'
        }`}
      >
        <span className={selected ? 'text-foreground' : 'text-muted'}>
          {selected ? getLabel(selected) : placeholder}
        </span>
        <span className="text-xs text-muted">▼</span>
      </button>

      {open && (
        <div className="relative z-50 mt-1 rounded-md border border-border bg-surface shadow-lg">
          <div className="border-b border-border p-2">
            <input
              type="text"
              autoFocus
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                onQueryChange?.(e.target.value)
              }}
              placeholder={searchPlaceholder}
              className="w-full rounded-md border border-border-strong bg-background px-3 py-1.5 text-sm text-foreground outline-none focus:border-primary"
            />
          </div>
          <ul className="max-h-56 overflow-auto py-1">
            {loading ? (
              <li className="px-3.5 py-2 text-sm text-muted">جاري التحميل...</li>
            ) : filtered.length === 0 ? (
              <li className="px-3.5 py-2 text-sm text-muted">لا توجد نتائج.</li>
            ) : (
              filtered.map((o) => {
                const val = getValue(o)
                const isSelected = String(val) === String(value)
                return (
                  <li
                    key={val}
                    onClick={() => {
                      onChange(String(val))
                      setOpen(false)
                    }}
                    className={`cursor-pointer px-3.5 py-2 text-sm transition hover:bg-background ${
                      isSelected ? 'bg-background font-medium text-primary' : 'text-foreground'
                    }`}
                  >
                    {getLabel(o)}
                  </li>
                )
              })
            )}
          </ul>
        </div>
      )}
    </div>
  )
}
