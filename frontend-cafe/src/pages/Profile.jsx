import { useEffect, useState } from 'react'
import { useAuth } from '../context/AuthContext'
import client from '../api/client'
import { SkeletonCard } from '../components/ui/Skeleton'

export default function Profile() {
  const { user } = useAuth()
  const [addresses, setAddresses] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    client
      .get('/cafe/profile')
      .then((res) => setAddresses(res.data?.data?.addresses ?? res.data?.addresses ?? []))
      .catch(() => setError('فشل تحميل بيانات الملف.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="space-y-6 pt-6">
        <div>
          <div className="h-8 w-48 rounded-md bg-border animate-pulse" />
          <div className="mt-2 h-4 w-64 rounded-md bg-border animate-pulse" />
        </div>
        <div className="grid gap-6 md:grid-cols-2">
          <SkeletonCard />
          <SkeletonCard />
        </div>
      </div>
    )
  }

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">الملف الشخصي</h1>
        <p className="mt-1 text-muted">معلومات حسابك وعناوينك</p>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 md:grid-cols-2">
        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <h2 className="mb-4 font-semibold text-foreground">معلومات المستخدم</h2>
          <div className="space-y-3 text-sm">
            <div className="flex justify-between"><span className="text-muted">الاسم</span><span>{user?.name}</span></div>
            <div className="flex justify-between"><span className="text-muted">البريد</span><span>{user?.email}</span></div>
            <div className="flex justify-between"><span className="text-muted">الجوال</span><span>{user?.mobile_number ?? '-'}</span></div>
            <div className="flex justify-between"><span className="text-muted">نوع الحساب</span><span>مدير مقهى</span></div>
          </div>
        </div>

        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
          <h2 className="mb-4 font-semibold text-foreground">عناويني</h2>
          {addresses.length === 0 ? (
            <div className="text-sm text-muted">لا توجد عناوين. أضف عنوانًا من صفحة العناوين.</div>
          ) : (
            <ul className="space-y-3 text-sm">
              {addresses.map((a) => (
                <li key={a.id} className="rounded-lg border border-border bg-background p-3">
                  <div className="flex justify-between"><span className="text-muted">الاسم</span><span>{a.name}</span></div>
                  <div className="flex justify-between"><span className="text-muted">المدينة</span><span>{a.city ?? '-'}</span></div>
                  <div className="flex justify-between"><span className="text-muted">أرقام التواصل</span><span>{(a.contact_phones ?? []).join('، ') || '-'}</span></div>
                  <div className="flex justify-between"><span className="text-muted">الحالة</span><span>{a.is_active ? 'نشط' : 'غير نشط'}</span></div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </>
  )
}
