import { useEffect, useRef, useState } from 'react'
import { Bell, Check, Trash2 } from 'lucide-react'
import client from '../api/client'
import ConfirmDialog from './ConfirmDialog'

export default function NotificationBell() {
  const [open, setOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)
  const [confirmId, setConfirmId] = useState(null)
  const dropdownRef = useRef(null)

  const fetchNotifications = async () => {
    try {
      const res = await client.get('/notifications?per_page=20')
      setNotifications(res.data?.data ?? [])
    } catch (err) {
      // silently fail
    }
  }

  const fetchUnreadCount = async () => {
    try {
      const res = await client.get('/notifications/unread-count')
      setUnreadCount(res.data?.count ?? 0)
    } catch (err) {
      // silently fail
    }
  }

  useEffect(() => {
    fetchUnreadCount()
    const interval = setInterval(fetchUnreadCount, 30000)
    return () => clearInterval(interval)
  }, [])

  useEffect(() => {
    if (open) {
      setLoading(true)
      fetchNotifications().finally(() => setLoading(false))
    }
  }, [open])

  useEffect(() => {
    function handleClickOutside(e) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const markRead = async (id) => {
    try {
      await client.put(`/notifications/${id}/read`)
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      )
      setUnreadCount((c) => Math.max(0, c - 1))
    } catch (err) {
      // silently fail
    }
  }

  const markAllRead = async () => {
    try {
      await client.put('/notifications/mark-all-read')
      setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })))
      setUnreadCount(0)
    } catch (err) {
      // silently fail
    }
  }

  const remove = async (id) => {
    try {
      await client.delete(`/notifications/${id}`)
      const removed = notifications.find((n) => n.id === id)
      setNotifications((prev) => prev.filter((n) => n.id !== id))
      if (removed && !removed.read_at) {
        setUnreadCount((c) => Math.max(0, c - 1))
      }
    } catch (err) {
      // silently fail
    }
  }

  const handleClick = (n) => {
    if (!n.read_at) markRead(n.id)
    if (n.link) {
      window.location.href = n.link
    }
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="relative rounded-full p-2 text-muted hover:bg-surface hover:text-foreground"
      >
        <Bell className="h-5 w-5" />
        {unreadCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute left-0 top-full z-50 mt-2 w-80 rounded-lg border border-border bg-white shadow-lg lg:left-auto lg:right-0">
          <div className="flex items-center justify-between border-b border-border px-4 py-3">
            <span className="font-semibold text-foreground">الإشعارات</span>
            {unreadCount > 0 && (
              <button
                onClick={markAllRead}
                className="flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <Check className="h-3 w-3" />
                تحديد الكل كمقروء
              </button>
            )}
          </div>
          <div className="max-h-80 overflow-y-auto">
            {loading ? (
              <div className="px-4 py-6 text-center text-sm text-muted">جاري التحميل...</div>
            ) : notifications.length === 0 ? (
              <div className="px-4 py-6 text-center text-sm text-muted">لا توجد إشعارات</div>
            ) : (
              notifications.map((n) => (
                <div
                  key={n.id}
                  className={`flex cursor-pointer items-start gap-2 border-b border-border px-4 py-3 last:border-0 hover:bg-surface ${
                    n.read_at ? 'opacity-70' : 'bg-primary/5'
                  }`}
                >
                  <div className="flex-1" onClick={() => handleClick(n)}>
                    <div className="text-sm font-medium text-foreground">{n.title}</div>
                    {n.message && <div className="text-xs text-muted">{n.message}</div>}
                    <div className="mt-1 text-[10px] text-muted">
                      {n.created_at ? new Date(n.created_at).toLocaleString('ar-LY') : ''}
                    </div>
                  </div>
                  <div className="flex flex-col gap-1">
                    {!n.read_at && (
                      <button
                        onClick={() => markRead(n.id)}
                        className="rounded p-1 text-muted hover:bg-background hover:text-success"
                        title="تحديد كمقروء"
                      >
                        <Check className="h-3.5 w-3.5" />
                      </button>
                    )}
                    <button
                      onClick={() => setConfirmId(n.id)}
                      className="rounded p-1 text-muted hover:bg-background hover:text-danger"
                      title="حذف"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      <ConfirmDialog
        open={confirmId !== null}
        message="هل تريد حذف هذا الإشعار؟ لا يمكن التراجع عن هذا الإجراء."
        onCancel={() => setConfirmId(null)}
        onConfirm={async () => {
          const id = confirmId
          setConfirmId(null)
          await remove(id)
        }}
      />
    </div>
  )
}
