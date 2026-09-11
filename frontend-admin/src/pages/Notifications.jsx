import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import ConfirmDialog from '../components/ConfirmDialog'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

export default function Notifications() {
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [filter, setFilter] = useState('all')
  const [confirmId, setConfirmId] = useState(null)

  const fetchNotifications = async () => {
    setLoading(true)
    try {
      const params = filter === 'unread' ? '?unread_only=1' : ''
      const res = await client.get(`/notifications${params}`)
      setNotifications(res.data?.data ?? [])
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الإشعارات')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchNotifications()
  }, [filter])

  const markRead = async (id, e) => {
    e?.stopPropagation()
    try {
      await client.put(`/notifications/${id}/read`)
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      )
    } catch (err) {
      setError(err.response?.data?.message || 'فشل التحديد كمقروء')
    }
  }

  const markAllRead = async () => {
    try {
      await client.put('/notifications/mark-all-read')
      setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })))
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديد الكل كمقروء')
    }
  }

  const remove = async (id) => {
    try {
      await client.delete(`/notifications/${id}`)
      setNotifications((prev) => prev.filter((n) => n.id !== id))
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الحذف')
    }
  }

  const askRemove = (id, e) => {
    e?.stopPropagation()
    setConfirmId(id)
  }

  const handleClick = (n) => {
    navigate(`/notifications/${n.id}`)
  }

  if (loading) return <PageSkeleton />

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">الإشعارات</h1>
        <div className="flex flex-wrap items-center gap-2">
          <div className="flex rounded-md border border-border bg-surface p-1">
            <button
              onClick={() => setFilter('all')}
              className={`rounded px-3 py-1 text-sm ${filter === 'all' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}
            >
              الكل
            </button>
            <button
              onClick={() => setFilter('unread')}
              className={`rounded px-3 py-1 text-sm ${filter === 'unread' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}
            >
              غير المقروء
            </button>
          </div>
          <Button variant="secondary" size="sm" onClick={markAllRead}>تحديد الكل كمقروء</Button>
        </div>
      </header>

      {error && <div className="mt-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mt-4 rounded-lg border border-border bg-white shadow-sm">
        {notifications.length === 0 ? (
          <div className="p-8 text-center text-muted">لا توجد إشعارات</div>
        ) : (
          <div className="divide-y divide-border">
            {notifications.map((n) => (
              <div
                key={n.id}
                onClick={() => handleClick(n)}
                className={`flex cursor-pointer items-start gap-4 p-4 transition hover:bg-surface ${n.read_at ? 'opacity-70' : 'bg-primary/5'}`}
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-foreground">{n.title}</span>
                    {!n.read_at && <Badge variant="primary">جديد</Badge>}
                  </div>
                  {n.message && <div className="mt-1 text-sm text-muted">{n.message}</div>}
                  <div className="mt-1 text-xs text-muted">
                    {n.created_at ? new Date(n.created_at).toLocaleString('ar-LY') : ''}
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  {!n.read_at && (
                    <Button variant="ghost" size="sm" onClick={(e) => markRead(n.id, e)} title="تحديد كمقروء">
                      مقروء
                    </Button>
                  )}
                  <Button variant="danger" size="sm" onClick={(e) => askRemove(n.id, e)}>حذف</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

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
    </>
  )
}
