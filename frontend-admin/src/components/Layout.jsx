import { useEffect, useMemo, useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { Menu, Search } from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import Nav, { useVisibleGroups } from './Nav'
import CommandPalette from './CommandPalette'
import NotificationBell from './NotificationBell'
import QuickOrderModal from './QuickOrderModal'
import { useLiveCounts } from '../hooks/useLiveCounts'

export default function Layout() {
  const { user, ready } = useAuth()
  if (!ready) return null
  if (!user) return <Navigate to="/login" replace />
  return <Shell />
}

function Shell() {
  const location = useLocation()
  const counts = useLiveCounts()
  const visibleGroups = useVisibleGroups()
  const [drawer, setDrawer] = useState(false)
  const [palette, setPalette] = useState(false)
  const [quickOrder, setQuickOrder] = useState(false)

  const pages = useMemo(() => visibleGroups.flatMap((g) => g.links.map((l) => ({ to: l.to, label: l.label, group: g.title }))), [visibleGroups])

  // Route change closes the phone drawer.
  useEffect(() => { setDrawer(false) }, [location.pathname])

  // Ctrl/⌘+K anywhere opens the palette; Esc closes the drawer.
  useEffect(() => {
    const onKey = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setPalette((v) => !v)
      } else if (e.key === 'Escape') {
        setDrawer(false)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const navProps = { counts, onSearch: () => setPalette(true), onQuickOrder: () => setQuickOrder(true) }

  return (
    <div className="flex h-svh flex-col overflow-hidden bg-background lg:flex-row">
      {/* Phone top bar. It carries the sidebar's colour and its own safe-area
          padding, so when the app is installed the bar and the status bar above
          it read as one surface instead of a white strip under a dark one. */}
      <header className="flex w-full min-w-0 items-center gap-1 bg-sidebar px-2 pb-2 pt-[calc(0.5rem+env(safe-area-inset-top))] text-sidebar-fg lg:hidden">
        <button type="button" onClick={() => setDrawer(true)} className="sb-icon-btn" aria-label="فتح القائمة"><Menu className="h-5 w-5" /></button>
        <img src="/favicon.svg" alt="" className="h-8 w-8 rounded-md bg-sidebar-fg object-contain p-0.5" />
        <div className="flex-1 truncate font-bold">الساحل</div>
        <button type="button" onClick={() => setPalette(true)} className="sb-icon-btn" aria-label="بحث"><Search className="h-5 w-5" /></button>
        <NotificationBell tone="dark" />
      </header>

      {/* Desktop sidebar */}
      <div className="hidden h-full shrink-0 lg:block">
        <Nav {...navProps} />
      </div>

      {/* Phone drawer */}
      <div className={`fixed inset-0 z-[9000] overflow-hidden lg:hidden ${drawer ? '' : 'pointer-events-none'}`} aria-hidden={!drawer}>
        <div className={`absolute inset-0 bg-black/50 transition-opacity motion-reduce:transition-none ${drawer ? 'opacity-100' : 'opacity-0'}`} onClick={() => setDrawer(false)} />
        <div className={`absolute inset-y-0 right-0 w-[min(84vw,300px)] pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)] shadow-lg transition-transform duration-200 motion-reduce:transition-none ${drawer ? 'translate-x-0' : 'translate-x-full'}`}>
          <Nav {...navProps} drawer onCloseDrawer={() => setDrawer(false)} />
        </div>
      </div>

      <main className="min-w-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto bg-white px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] lg:px-6 lg:pb-8">
        <Outlet />
      </main>

      <CommandPalette open={palette} onClose={() => setPalette(false)} pages={pages} />
      <QuickOrderModal open={quickOrder} onClose={() => setQuickOrder(false)} onCreated={() => setQuickOrder(false)} />
    </div>
  )
}
