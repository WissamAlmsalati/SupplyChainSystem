import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Send, Inbox, X } from 'lucide-react'
import client from '../api/client'
import Button from '../components/ui/Button'
import ConfirmDialog from '../components/ConfirmDialog'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'
import { useAuth } from '../context/AuthContext'
import { formatDateTime } from '../lib/wallet'

const AUDIENCES = [
  { value: 'customers', label: 'كل الزبائن (المقاهي)' },
  { value: 'delegates', label: 'كل المناديب' },
  { value: 'admins', label: 'فريق الإدارة' },
  { value: 'all', label: 'كل المستخدمين' },
  { value: 'users', label: 'مستخدمين محددين' },
]

const fieldClass = 'w-full rounded-md border border-border-strong bg-surface px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none'

// Inbox for the signed-in user, plus (with NOTIFICATIONS_SEND) sending
// announcements to customers, delegates or admins and a history of what went out.
export default function Notifications() {
  const { hasPermission } = useAuth()
  const canSend = hasPermission('NOTIFICATIONS_SEND')
  const [tab, setTab] = useState('inbox')

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">الإشعارات</h1>
          <p className="mt-1 text-sm text-muted">{tab === 'inbox' ? 'ما وصلك من النظام' : 'أرسل إعلاناً يظهر للمستخدمين في تطبيقاتهم'}</p>
        </div>
        {canSend && (
          <div className="flex rounded-md border border-border bg-surface p-1">
            <button onClick={() => setTab('inbox')} className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-sm ${tab === 'inbox' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}><Inbox className="h-4 w-4" /> الواردة</button>
            <button onClick={() => setTab('send')} className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-sm ${tab === 'send' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}><Send className="h-4 w-4" /> إرسال إشعار</button>
          </div>
        )}
      </header>
      {tab === 'inbox' ? <InboxTab /> : <SendTab />}
    </>
  )
}

function InboxTab() {
  const navigate = useNavigate()
  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [filter, setFilter] = useState('all')
  const [confirmId, setConfirmId] = useState(null)

  const load = async () => {
    setLoading(true)
    try {
      const res = await client.get('/notifications', { params: filter === 'unread' ? { unread_only: 1, per_page: 50 } : { per_page: 50 } })
      setNotifications(res.data?.data ?? [])
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الإشعارات')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => { load() }, [filter])

  const markRead = async (id, e) => {
    e?.stopPropagation()
    try {
      await client.put(`/notifications/${id}/read`)
      setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)))
    } catch (err) {
      setError(err.response?.data?.message || 'فشل التحديد كمقروء')
    }
  }
  const markAllRead = async () => {
    try {
      await client.put('/notifications/mark-all-read')
      setNotifications((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })))
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

  if (loading) return <PageSkeleton />

  return (
    <>
      <div className="mt-4 flex flex-wrap items-center gap-2">
        <div className="flex rounded-md border border-border bg-surface p-1">
          <button onClick={() => setFilter('all')} className={`rounded px-3 py-1 text-sm ${filter === 'all' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}>الكل</button>
          <button onClick={() => setFilter('unread')} className={`rounded px-3 py-1 text-sm ${filter === 'unread' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background'}`}>غير المقروء</button>
        </div>
        <Button variant="secondary" size="sm" onClick={markAllRead}>تحديد الكل كمقروء</Button>
      </div>

      {error && <div className="mt-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="mt-4 rounded-lg border border-border bg-white shadow-sm">
        {notifications.length === 0 ? (
          <div className="p-8 text-center text-muted">لا توجد إشعارات</div>
        ) : (
          <div className="divide-y divide-border">
            {notifications.map((n) => (
              <div key={n.id} onClick={() => navigate(`/notifications/${n.id}`)} className={`flex cursor-pointer items-start gap-4 p-4 transition hover:bg-surface ${n.read_at ? 'opacity-70' : 'bg-primary/5'}`}>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-foreground">{n.title}</span>
                    {!n.read_at && <Badge variant="primary">جديد</Badge>}
                    {n.type === 'announcement' && <Badge variant="info">إعلان</Badge>}
                  </div>
                  {n.message && <div className="mt-1 text-sm text-muted">{n.message}</div>}
                  <div className="mt-1 text-xs text-muted">{formatDateTime(n.created_at)}</div>
                </div>
                <div className="flex items-center gap-1">
                  {!n.read_at && <Button variant="ghost" size="sm" onClick={(e) => markRead(n.id, e)}>مقروء</Button>}
                  <Button variant="danger" size="sm" onClick={(e) => { e.stopPropagation(); setConfirmId(n.id) }}>حذف</Button>
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
        onConfirm={async () => { const id = confirmId; setConfirmId(null); await remove(id) }}
      />
    </>
  )
}

function SendTab() {
  const [form, setForm] = useState({ audience: 'customers', title: '', message: '', link: '' })
  const [picked, setPicked] = useState([])
  const [userQuery, setUserQuery] = useState('')
  const [userHits, setUserHits] = useState([])
  const [sending, setSending] = useState(false)
  const [result, setResult] = useState('')
  const [error, setError] = useState('')
  const [history, setHistory] = useState([])
  const [confirm, setConfirm] = useState(false)

  const loadHistory = () => client.get('/notifications/sent').then((r) => setHistory(r.data?.data ?? [])).catch(() => {})
  useEffect(() => { loadHistory() }, [])

  // User picker: search by name or phone, keep chips.
  useEffect(() => {
    if (form.audience !== 'users' || userQuery.trim().length < 2) { setUserHits([]); return }
    const t = setTimeout(() => {
      client.get('/users', { params: { search: userQuery.trim(), per_page: 8 } })
        .then((r) => setUserHits((r.data?.data ?? []).filter((u) => !picked.some((p) => p.id === u.id))))
        .catch(() => setUserHits([]))
    }, 250)
    return () => clearTimeout(t)
  }, [userQuery, form.audience, picked])

  const audienceLabel = AUDIENCES.find((a) => a.value === form.audience)?.label
  const ready = form.title.trim() && (form.audience !== 'users' || picked.length > 0)

  const send = async () => {
    setConfirm(false)
    setSending(true)
    setError('')
    setResult('')
    try {
      const res = await client.post('/notifications/send', {
        audience: form.audience,
        user_ids: form.audience === 'users' ? picked.map((u) => u.id) : undefined,
        title: form.title.trim(),
        message: form.message.trim() || null,
        link: form.link.trim() || null,
      })
      setResult(res.data?.message || `أُرسل إلى ${res.data?.data?.sent} مستخدم`)
      setForm((f) => ({ ...f, title: '', message: '', link: '' }))
      setPicked([])
      loadHistory()
    } catch (err) {
      const errors = err.response?.data?.errors
      setError(errors ? Object.values(errors).flat()[0] : err.response?.data?.message || 'فشل الإرسال')
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="mt-4 grid gap-6 lg:grid-cols-5">
      <div className="rounded-lg border border-border bg-white p-5 shadow-sm lg:col-span-3">
        <div className="grid gap-4">
          <label className="text-sm font-medium text-muted">إلى من؟
            <select className={`${fieldClass} mt-1.5`} value={form.audience} onChange={(e) => setForm({ ...form, audience: e.target.value })}>
              {AUDIENCES.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
            </select>
          </label>

          {form.audience === 'users' && (
            <div>
              <label className="text-sm font-medium text-muted">ابحث بالاسم أو رقم الجوال
                <input className={`${fieldClass} mt-1.5`} value={userQuery} onChange={(e) => setUserQuery(e.target.value)} placeholder="مثال: مقهى الزاوية أو 0912…" />
              </label>
              {userHits.length > 0 && (
                <div className="mt-1 max-h-48 overflow-y-auto rounded-md border border-border bg-surface">
                  {userHits.map((u) => (
                    <button key={u.id} type="button" onClick={() => { setPicked([...picked, u]); setUserQuery(''); setUserHits([]) }} className="flex w-full items-center justify-between px-3 py-2 text-right text-sm hover:bg-background">
                      <span>{u.name} <span className="text-xs text-muted">{u.user_type?.name === 'customer' ? 'زبون' : u.user_type?.name === 'delegate' ? 'مندوب' : 'إدارة'}</span></span>
                      <bdi className="text-xs text-muted">{u.mobile_number}</bdi>
                    </button>
                  ))}
                </div>
              )}
              {picked.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {picked.map((u) => (
                    <span key={u.id} className="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary">
                      {u.name}
                      <button type="button" onClick={() => setPicked(picked.filter((p) => p.id !== u.id))} aria-label={`إزالة ${u.name}`}><X className="h-3 w-3" /></button>
                    </span>
                  ))}
                </div>
              )}
            </div>
          )}

          <label className="text-sm font-medium text-muted">العنوان
            <input className={`${fieldClass} mt-1.5`} maxLength={150} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="مثال: عرض نهاية الأسبوع على البن" />
          </label>
          <label className="text-sm font-medium text-muted">النص
            <textarea className={`${fieldClass} mt-1.5 min-h-28`} maxLength={2000} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} placeholder="التفاصيل اللي تبي المستخدم يقراها" />
          </label>
          <label className="text-sm font-medium text-muted">رابط داخل التطبيق (اختياري)
            <input className={`${fieldClass} mt-1.5`} dir="ltr" value={form.link} onChange={(e) => setForm({ ...form, link: e.target.value })} placeholder="/products أو /orders/58" />
          </label>

          {error && <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}
          {result && <div className="rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">{result}</div>}

          <div className="flex justify-end">
            <Button variant="primary" disabled={!ready || sending} onClick={() => setConfirm(true)}><Send className="h-4 w-4" /> {sending ? 'جاري الإرسال…' : 'إرسال'}</Button>
          </div>
        </div>
      </div>

      <div className="lg:col-span-2">
        <div className="rounded-lg border border-border bg-white p-5 shadow-sm">
          <div className="mb-3 font-semibold text-foreground">معاينة كما ستظهر</div>
          <div className="rounded-lg border border-border bg-primary/5 p-4">
            <div className="font-semibold text-foreground">{form.title || 'عنوان الإشعار'}</div>
            <div className="mt-1 whitespace-pre-wrap text-sm text-muted">{form.message || 'نص الإشعار'}</div>
            <div className="mt-2 text-xs text-muted">الآن · {audienceLabel}{form.audience === 'users' && picked.length ? ` (${picked.length})` : ''}</div>
          </div>
        </div>

        <div className="mt-4 rounded-lg border border-border bg-white p-5 shadow-sm">
          <div className="mb-3 font-semibold text-foreground">آخر ما أُرسل</div>
          {history.length === 0 ? <div className="text-sm text-muted">لم يُرسل أي إعلان بعد.</div> : (
            <div className="divide-y divide-border">
              {history.map((h, i) => (
                <div key={i} className="py-3 first:pt-0 last:pb-0">
                  <div className="flex items-start justify-between gap-2">
                    <div className="font-medium text-foreground">{h.title}</div>
                    <div className="shrink-0 text-xs text-muted tabular-nums">قرأه {h.read} من {h.recipients}</div>
                  </div>
                  {h.message && <div className="mt-0.5 line-clamp-2 text-sm text-muted">{h.message}</div>}
                  <div className="mt-1 text-xs text-muted">{formatDateTime(h.sent_at)}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={confirm}
        title="إرسال الإشعار"
        message={`سيصل «${form.title}» إلى ${form.audience === 'users' ? `${picked.length} مستخدم` : audienceLabel}. متأكد؟`}
        confirmLabel="إرسال"
        onCancel={() => setConfirm(false)}
        onConfirm={send}
      />
    </div>
  )
}
