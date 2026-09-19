import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { Skeleton } from '../components/ui/Skeleton'

// The inbox: what happened to your orders, your wallet, and what the office announced.
export default function Notifications() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const door = user?.user_type?.name === 'delegate' ? 'delegate' : 'customer'
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async (p = page) => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/${door}/notifications`, { params: { page: p, per_page: 20 } })
      setItems(res.data?.data ?? [])
      setMeta(res.data?.meta ?? null)
    } catch (err) {
      setError(err.response?.data?.message || 'تعذر تحميل الإشعارات')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => { load(page) }, [page, door])

  const announce = () => window.dispatchEvent(new Event('notifications:changed'))

  const open = async (n) => {
    if (!n.read_at) {
      client.patch(`/${door}/notifications/${n.id}/read`).then(announce).catch(() => {})
      setItems((list) => list.map((x) => (x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x)))
    }
    // An order notification opens its order; this app serves both roles at /orders/:id.
    if (n.entity_type === 'order' && n.entity_id) navigate(`/orders/${n.entity_id}`)
    else if (n.link && n.link.startsWith('/')) navigate(n.link)
  }

  const markAll = async () => {
    await client.patch(`/${door}/notifications/mark-all-read`).catch(() => {})
    announce()
    load(page)
  }

  const unread = items.filter((n) => !n.read_at).length

  return (
    <>
      <header className="mb-6 flex items-center justify-between gap-4 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">الإشعارات</h1>
        {unread > 0 && <button onClick={markAll} className="rounded-lg border border-border px-3 py-1.5 text-sm text-muted hover:text-foreground">تحديد الكل كمقروء</button>}
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      {loading ? (
        <div className="space-y-3">{[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-16 rounded-xl" />)}</div>
      ) : items.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface p-10 text-center text-muted">لا توجد إشعارات بعد. ستصلك هنا أخبار طلباتك ومحفظتك.</div>
      ) : (
        <ul className="space-y-2">
          {items.map((n) => (
            <li key={n.id}>
              <button onClick={() => open(n)} className={`w-full rounded-xl border p-4 text-right shadow-sm transition hover:border-primary ${n.read_at ? 'border-border bg-surface' : 'border-primary/30 bg-primary/5'}`}>
                <div className="flex items-start justify-between gap-3">
                  <span className={`text-sm ${n.read_at ? 'font-medium' : 'font-extrabold'} text-foreground`}>{n.title}</span>
                  {!n.read_at && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                </div>
                {n.message && <div className="mt-1 text-sm text-muted">{n.message}</div>}
                <div className="mt-2 text-xs text-muted">{new Date(n.created_at).toLocaleString('ar-LY')}</div>
              </button>
            </li>
          ))}
        </ul>
      )}

      {meta && meta.last_page > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3 text-sm">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40">السابق</button>
          <span className="text-muted">{meta.current_page} / {meta.last_page}</span>
          <button disabled={!meta.has_more} onClick={() => setPage(page + 1)} className="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40">التالي</button>
        </div>
      )}
    </>
  )
}
