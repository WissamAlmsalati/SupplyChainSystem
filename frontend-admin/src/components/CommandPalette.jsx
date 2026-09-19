import { useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router-dom'
import {
  Search, ShoppingCart, Users, Truck, ShieldCheck, Package, Boxes, Tags, MapPin, Warehouse, Map, BanknoteArrowUp,
  Wallet, HandCoins, Undo2, Megaphone, Bell, ClipboardList, CornerDownLeft, Clock, Zap, FileText, Loader2, X,
} from 'lucide-react'
import client from '../api/client'
import { fold, highlightParts } from '../lib/arabicText'

const GROUP_ICONS = {
  orders: ShoppingCart, customers: Users, delegates: Truck, staff: ShieldCheck, products: Package, variants: Boxes,
  categories: Tags, addresses: MapPin, warehouses: Warehouse, zones: Map, topups: BanknoteArrowUp, wallets: Wallet,
  settlements: HandCoins, returns: Undo2, promos: Megaphone, roles: ShieldCheck, notifications: Bell, activity: ClipboardList,
}

const RECENT_KEY = 'palette-recent'
const readRecent = () => {
  try { return JSON.parse(window.localStorage.getItem(RECENT_KEY)) ?? [] } catch { return [] }
}

function Marked({ text, terms, active }) {
  return highlightParts(text, terms).map((part, i) => (part.hit
    ? <mark key={i} className={`rounded-sm bg-transparent font-extrabold ${active ? 'text-primary-foreground underline decoration-2 underline-offset-2' : 'text-primary'}`}>{part.text}</mark>
    : <span key={i}>{part.text}</span>))
}

/**
 * ⌘K / Ctrl+K. One box over everything: pages and actions are matched here,
 * everything else by GET /search, which looks through every module the user
 * may see. Words may match different fields ("احمد طرابلس"), spelling variants
 * and Arabic digits are tolerated, and "#52" opens row 52.
 *
 * Arrows move, Enter opens, Tab narrows to one kind, Esc clears then closes.
 */
export default function CommandPalette({ open, onClose, pages, actions = [] }) {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [scope, setScope] = useState(null)
  const [scopes, setScopes] = useState([])
  const [groups, setGroups] = useState([])
  const [searched, setSearched] = useState('')
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)
  const [cursor, setCursor] = useState(0)
  const [recent, setRecent] = useState(readRecent)
  const inputRef = useRef(null)
  const listRef = useRef(null)

  useEffect(() => {
    if (open) {
      setQuery(''); setScope(null); setGroups([]); setSearched(''); setFailed(false); setCursor(0)
      setRecent(readRecent())
      setTimeout(() => inputRef.current?.focus(), 0)
    }
  }, [open])

  const q = query.trim()
  const terms = useMemo(() => q.replace(/^#/, '').split(/\s+/).filter(Boolean), [q])
  const searchable = q.replace(/^#/, '').length >= 2 || /^#?\d+$/.test(q)

  useEffect(() => {
    if (!open || !searchable) {
      setGroups([]); setSearched(''); setLoading(false)
      return
    }
    const controller = new AbortController()
    setLoading(true)
    const timer = setTimeout(() => {
      client.get('/search', { params: { q, only: scope || undefined }, signal: controller.signal })
        .then((res) => {
          setGroups(res.data?.groups ?? [])
          if (res.data?.scopes?.length) setScopes(res.data.scopes)
          setSearched(q)
          setFailed(false)
          setLoading(false)
        })
        .catch((err) => {
          if (controller.signal.aborted || err.code === 'ERR_CANCELED') return
          setGroups([]); setFailed(true); setLoading(false)
        })
    }, 180)
    return () => { clearTimeout(timer); controller.abort() }
  }, [q, scope, open, searchable])

  const sections = useMemo(() => {
    // Pages and actions are matched here with the same folding the API uses.
    const needles = fold(q).split(/\s+/).filter(Boolean)
    const localMatch = (label, extra = '') => needles.every((t) => fold(`${label} ${extra}`).includes(t))
    const out = []
    if (!scope) {
      const acts = actions.filter((a) => localMatch(a.label, a.keywords)).map((a) => ({ key: `a:${a.label}`, title: a.label, subtitle: a.hint, Icon: a.Icon ?? Zap, run: a.run }))
      const pageHits = pages.filter((p) => localMatch(p.label, p.group)).slice(0, q ? 6 : 7).map((p) => ({ key: `p:${p.to}`, title: p.label, subtitle: p.group, Icon: FileText, url: p.to }))
      if (!q && recent.length) out.push({ key: 'recent', label: 'فتحتها مؤخراً', items: recent.map((r) => ({ ...r, key: `r:${r.url}:${r.title}`, Icon: Clock })) })
      if (acts.length) out.push({ key: 'actions', label: 'إجراءات', items: acts })
      if (pageHits.length) out.push({ key: 'pages', label: 'الصفحات', items: pageHits })
    }
    for (const g of groups) {
      out.push({
        key: g.key,
        label: g.label,
        items: [
          ...g.items.map((it) => ({ ...it, key: `${g.key}:${it.id}`, Icon: GROUP_ICONS[g.key] ?? Search, remember: true, kind: g.label })),
          ...(g.has_more && !scope ? [{ key: `more:${g.key}`, title: `عرض المزيد من ${g.label}`, more: g.key, Icon: Search }] : []),
        ],
      })
    }
    return out
  }, [actions, pages, groups, recent, q, scope])

  const flat = useMemo(() => sections.flatMap((s) => s.items), [sections])
  useEffect(() => setCursor(0), [q, scope, groups])
  useEffect(() => { listRef.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }) }, [cursor])

  const choose = (item) => {
    if (!item) return
    if (item.more) { setScope(item.more); inputRef.current?.focus(); return }
    if (item.remember) {
      const entry = { title: item.title, subtitle: item.kind, url: item.url }
      const next = [entry, ...readRecent().filter((r) => r.url !== entry.url || r.title !== entry.title)].slice(0, 6)
      try { window.localStorage.setItem(RECENT_KEY, JSON.stringify(next)) } catch { /* private mode */ }
    }
    onClose()
    if (item.run) item.run()
    else navigate(item.url)
  }

  const cycleScope = (back) => {
    const order = [null, ...scopes.map((s) => s.key)]
    const at = order.indexOf(scope)
    setScope(order[(at + (back ? -1 : 1) + order.length) % order.length])
  }

  const onKey = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setCursor((c) => Math.min(c + 1, flat.length - 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setCursor((c) => Math.max(c - 1, 0)) }
    else if (e.key === 'Enter') { e.preventDefault(); choose(flat[cursor]) }
    else if (e.key === 'Tab' && scopes.length) { e.preventDefault(); cycleScope(e.shiftKey) }
    else if (e.key === 'Escape') { if (query || scope) { setQuery(''); setScope(null) } else onClose() }
  }

  if (!open) return null

  const waiting = loading && searched !== q
  const nothing = searchable && !waiting && !failed && flat.length === 0

  return createPortal(
    <div className="fixed inset-0 z-[9998] bg-black/40 p-3 backdrop-blur-sm sm:p-4 sm:pt-[10vh]" onMouseDown={onClose}>
      <div className="mx-auto flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-lg" onMouseDown={(e) => e.stopPropagation()} role="dialog" aria-label="بحث شامل">
        <div className="flex items-center gap-3 border-b border-border px-4">
          {waiting ? <Loader2 className="h-5 w-5 shrink-0 animate-spin text-primary" /> : <Search className="h-5 w-5 shrink-0 text-muted" />}
          <input
            ref={inputRef}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={onKey}
            placeholder="ابحث عن أي شيء: طلب، زبون، جوال، منتج، SKU، عنوان، مرجع شحن…"
            className="h-14 w-full bg-transparent text-base text-foreground outline-none placeholder:text-muted"
            autoComplete="off"
            spellCheck={false}
          />
          {query && <button type="button" onClick={() => { setQuery(''); inputRef.current?.focus() }} className="rounded p-1 text-muted hover:text-foreground" aria-label="مسح"><X className="h-4 w-4" /></button>}
        </div>

        {scopes.length > 0 && searchable && (
          <div className="flex gap-1.5 overflow-x-auto border-b border-border px-3 py-2 [scrollbar-width:none]">
            {[{ key: null, label: 'الكل' }, ...scopes].map((s) => (
              <button
                key={s.key ?? 'all'}
                type="button"
                onClick={() => { setScope(s.key); inputRef.current?.focus() }}
                className={`shrink-0 rounded-full border px-3 py-1 text-xs transition ${scope === s.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border text-muted hover:border-primary hover:text-primary'}`}
              >
                {s.label}
              </button>
            ))}
          </div>
        )}

        <div ref={listRef} className="min-h-0 flex-1 overflow-y-auto py-2 sm:max-h-[60vh]">
          {failed ? (
            <div className="px-4 py-8 text-center text-sm text-danger">تعذر البحث. تحقق من الاتصال وأعد المحاولة.</div>
          ) : nothing ? (
            <div className="px-4 py-8 text-center text-sm text-muted">
              لا توجد نتائج لـ «{q}»{scope ? ' في هذا القسم' : ''}.
              {scope && <button type="button" className="mr-1 text-primary underline" onClick={() => setScope(null)}>ابحث في الكل</button>}
            </div>
          ) : flat.length === 0 ? (
            <div className="px-4 py-8 text-center text-sm text-muted">اكتب حرفين على الأقل، أو رقماً مثل <bdi>#52</bdi></div>
          ) : sections.map((section) => (
            <div key={section.key} className="px-2 pb-1">
              <div className="px-2 pb-1 pt-2 text-xs text-muted">{section.label}</div>
              {section.items.map((item) => {
                const idx = flat.indexOf(item)
                const active = idx === cursor
                return (
                  <button
                    key={item.key}
                    type="button"
                    data-active={active}
                    onMouseMove={() => setCursor(idx)}
                    onClick={() => choose(item)}
                    className={`flex w-full items-center gap-3 rounded-md px-2 py-2 text-right text-sm ${active ? 'bg-primary text-primary-foreground' : 'text-foreground'} ${item.more ? 'opacity-80' : ''}`}
                  >
                    <item.Icon className={`h-4 w-4 shrink-0 ${active ? 'text-primary-foreground' : 'text-muted'}`} />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium" dir="auto"><Marked text={item.title} terms={item.more ? [] : terms} active={active} /></span>
                      {item.subtitle && <span className={`block truncate text-xs ${active ? 'text-primary-foreground/80' : 'text-muted'}`} dir="auto"><Marked text={item.subtitle} terms={terms} active={active} /></span>}
                    </span>
                    {item.badge && <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[11px] ${active ? 'border-primary-foreground/40 text-primary-foreground' : 'border-border text-muted'}`}>{item.badge}</span>}
                    {active && <CornerDownLeft className="h-3.5 w-3.5 shrink-0 opacity-80" />}
                  </button>
                )
              })}
            </div>
          ))}
        </div>

        <div className="hidden items-center gap-4 border-t border-border px-4 py-2 text-xs text-muted sm:flex">
          <span><kbd className="rounded border border-border bg-background px-1">↑↓</kbd> تنقل</span>
          <span><kbd className="rounded border border-border bg-background px-1">Enter</kbd> فتح</span>
          <span><kbd className="rounded border border-border bg-background px-1">Tab</kbd> تضييق القسم</span>
          <span><kbd className="rounded border border-border bg-background px-1">Esc</kbd> مسح ثم إغلاق</span>
          <span className="mr-auto">كل كلمة تطابق حقلاً مختلفاً: «احمد طرابلس»</span>
        </div>
      </div>
    </div>,
    document.body,
  )
}
