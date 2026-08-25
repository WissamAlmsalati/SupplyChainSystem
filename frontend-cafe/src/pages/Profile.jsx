import { useEffect, useState } from 'react'
import { useAuth } from '../context/AuthContext'
import client from '../api/client'

export default function Profile() {
  const { user } = useAuth()
  const [cafe, setCafe] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    client
      .get(`/cafes/${user?.cafe_id}`)
      .then((res) => setCafe(res.data?.data ?? res.data))
      .catch(() => setCafe(null))
      .finally(() => setLoading(false))
  }, [user])

  if (loading) return <div className="pt-6 text-muted">جاري التحميل...</div>

  return (
    <>
      <header className="mb-6 pt-6">
        <h1 className="text-2xl font-extrabold text-foreground">الملف الشخصي</h1>
        <p className="mt-1 text-muted">معلومات حسابك ومقهاك</p>
      </header>

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
          <h2 className="mb-4 font-semibold text-foreground">معلومات المقهى</h2>
          {cafe ? (
            <div className="space-y-3 text-sm">
              <div className="flex justify-between"><span className="text-muted">الاسم</span><span>{cafe.name}</span></div>
              <div className="flex justify-between"><span className="text-muted">معلومات التواصل</span><span>{cafe.contact_info ?? '-'}</span></div>
              <div className="flex justify-between"><span className="text-muted">الحالة</span><span>{cafe.is_active ? 'نشط' : 'غير نشط'}</span></div>
            </div>
          ) : (
            <div className="text-sm text-muted">فشل تحميل بيانات المقهى.</div>
          )}
        </div>
      </div>
    </>
  )
}
