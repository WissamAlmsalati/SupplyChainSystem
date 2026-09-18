import { useEffect, useState } from 'react'
import { Bell } from 'lucide-react'
import { NavLink } from 'react-router-dom'
import client from '../api/client'

// Unread counter that opens the full notifications page (the inbox lives
// there, together with sending announcements). Polls every 30s and on focus.
export default function NotificationBell({ tone = 'light' }) {
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let cancelled = false
    const load = () => client.get('/notifications/unread-count').then((r) => { if (!cancelled) setUnread(r.data?.count ?? 0) }).catch(() => {})
    load()
    const id = setInterval(load, 30000)
    window.addEventListener('focus', load)
    return () => { cancelled = true; clearInterval(id); window.removeEventListener('focus', load) }
  }, [])

  return (
    <NavLink
      to="/notifications"
      className={tone === 'dark' ? 'sb-icon-btn relative' : 'relative rounded-full p-2 text-muted hover:bg-surface hover:text-foreground'}
      aria-label={unread ? `الإشعارات، ${unread} غير مقروء` : 'الإشعارات'}
      title="الإشعارات"
    >
      <Bell className="h-5 w-5" />
      {unread > 0 && (
        <span className={`absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold ${tone === 'dark' ? 'bg-ember text-sidebar' : 'bg-danger text-white'}`}>
          {unread > 9 ? '9+' : unread}
        </span>
      )}
    </NavLink>
  )
}
