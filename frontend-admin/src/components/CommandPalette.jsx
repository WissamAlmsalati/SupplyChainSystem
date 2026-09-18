import { useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router-dom'
import { Search, ShoppingCart, Users, CornerDownLeft } from 'lucide-react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { statusLabels } from '../lib/status'

// ⌘K / Ctrl+K. Jumps to a page, an order (by number, customer or phone) or a
// customer. Results are grouped; arrows move, Enter opens, Esc closes.
export default function CommandPalette({ open, onClose, pages }) {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const [query, setQuery] = useState('')
  const [remote, setRemote] = useState({ orders: [], customers: [] })
  const [loading, setLoading] = useState(false)
  const [cursor, setCursor] = useState(0)
  const inputRef = useRef(null)

  useEffect(() => {
    if (open) {
      setQuery('')
      setRemote({ orders: [], customers: [] })
      setCursor(0)
      setTimeout(() => inputRef.current?.focus(), 0)
    }
  }, [open])

  const q = query.trim()

  useEffect(() => {
    if (!open || q.length < 2) {
      setRemote({ orders: [], customers: [] })
      return
    }
    let cancelled = false
    setLoading(true)
    const t = setTimeout(async () => {
      const [orders, customers] = await Promise.all([
        hasPermission('ORDERS_VIEW') ? client.get('/orders', { params: { search: q, per_page: 5 } }).then((r) => r.data?.data ?? []).catch(() => []) : [],
        hasPermission('USERS_VIEW') ? client.get('/users', { params: { search: q, user_type: 'customer', per_page: 5 } }).then((r) => r.data?.data ?? []).catch(() => []) : [],
      ])
      if (!cancelled) {
        setRemote({ orders, customers })
        setLoading(false)
      }
    }, 250)
    return () => {
      cancelled = true
      clearTimeout(t)
    }
  }, [q, open, hasPermission])

  const pageHits = useMemo(() => {
    const needle = q.toLowerCase()
    return pages.filter((p) => !needle || p.label.toLowerCase().includes(needle) || p.group.toLowerCase().includes(needle)).slice(0, needle ? 6 : 8)
  }, [pages, q])

  const items = useMemo(() => [
    ...pageHits.map((p) => ({ kind: 'page', key: `p:${p.to}`, label: p.label, hint: p.group, to: p.to, Icon: p.Icon })),
    ...remote.orders.map((o) => ({ kind: 'order', key: `o:${o.id}`, label: o.order_number, hint: `${o.user?.name ?? ''} · ${statusLabels[o.status] ?? o.status}`, to: `/orders/${o.id}`, Icon: ShoppingCart })),
    ...remote.customers.map((u) => ({ kind: 'customer', key: `u:${u.id}`, label: u.name, hint: u.mobile_number, to: `/users/${u.id}`, Icon: Users })),
  ], [pageHits, remote])

  useEffect(() => setCursor(0), [items.length, q])

  const go = (item) => {
    if (!item) return
    onClose()
    navigate(item.to)
  }

  const onKey = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setCursor((c) => Math.min(c + 1, items.length - 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setCursor((c) => Math.max(c - 1, 0)) }
    else if (e.key === 'Enter') { e.preventDefault(); go(items[cursor]) }
    else if (e.key === 'Escape') onClose()
  }

  if (!open) return null

  const groups = [
    ['الصفحات', items.filter((i) => i.kind === 'page')],
    ['الطلبات', items.filter((i) => i.kind === 'order')],
    ['الزبائن', items.filter((i) => i.kind === 'customer')],
  ].filter(([, list]) => list.length)

  return createPortal(
    <div className="fixed inset-0 z-[9998] bg-black/40 p-4 backdrop-blur-sm sm:pt-[12vh]" onMouseDown={onClose}>
      <div className="mx-auto w-full max-w-xl overflow-hidden rounded-xl border border-border bg-surface shadow-lg" onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-label="بحث سريع">
        <div className="flex items-center gap-3 border-b border-border px-4">
          <Search className="h-5 w-5 shrink-0 text-muted" />
          <input
            ref={inputRef}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={onKey}
            placeholder="اكتب اسم صفحة، رقم طلب، اسم زبون أو رقم جوال"
            className="h-14 w-full bg-transparent text-base text-foreground outline-none placeholder:text-muted"
          />
          {loading && <span className="text-xs text-muted">يبحث…</span>}
        </div>
        <div className="max-h-[60vh] overflow-y-auto py-2">
          {items.length === 0 ? (
            <div className="px-4 py-8 text-center text-sm text-muted">{q.length < 2 ? 'ابدأ الكتابة للبحث في الطلبات والزبائن' : 'لا توجد نتائج'}</div>
          ) : groups.map(([title, list]) => (
            <div key={title} className="px-2 pb-1">
              <div className="px-2 pb-1 pt-2 text-xs text-muted">{title}</div>
              {list.map((item) => {
                const idx = items.indexOf(item)
                const active = idx === cursor
                return (
                  <button
                    key={item.key}
                    type="button"
                    onMouseEnter={() => setCursor(idx)}
                    onClick={() => go(item)}
                    className={`flex w-full items-center gap-3 rounded-md px-2 py-2 text-right text-sm ${active ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}
                  >
                    {item.Icon && <item.Icon className={`h-4 w-4 shrink-0 ${active ? 'text-primary-foreground' : 'text-muted'}`} />}
                    <span className="flex-1 truncate font-medium" dir="auto">{item.label}</span>
                    <span className={`truncate text-xs ${active ? 'text-primary-foreground/80' : 'text-muted'}`} dir="auto">{item.hint}</span>
                    {active && <CornerDownLeft className="h-3.5 w-3.5 shrink-0 opacity-80" />}
                  </button>
                )
              })}
            </div>
          ))}
        </div>
        <div className="flex items-center gap-4 border-t border-border px-4 py-2 text-xs text-muted">
          <span><kbd className="rounded border border-border bg-background px-1">↑↓</kbd> تنقل</span>
          <span><kbd className="rounded border border-border bg-background px-1">Enter</kbd> فتح</span>
          <span><kbd className="rounded border border-border bg-background px-1">Esc</kbd> إغلاق</span>
        </div>
      </div>
    </div>,
    document.body,
  )
}
