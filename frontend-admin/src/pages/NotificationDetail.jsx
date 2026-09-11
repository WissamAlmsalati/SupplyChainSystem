import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import Badge from '../components/ui/Badge'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import { PageSkeleton } from '../components/ui/Skeleton'
import ConfirmDialog from '../components/ConfirmDialog'

export default function NotificationDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [notification, setNotification] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [confirmOpen, setConfirmOpen] = useState(false)

  useEffect(() => {
    const load = async () => {
      setLoading(true)
      setError('')
      try {
        const res = await client.get(`/notifications/${id}`)
        const data = res.data?.data ?? res.data
        setNotification(data)
        if (!data.read_at) {
          await client.put(`/notifications/${id}/read`)
          setNotification((prev) => (prev ? { ...prev, read_at: new Date().toISOString() } : prev))
        }
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل الإشعار')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const remove = async () => {
    try {
      await client.delete(`/notifications/${id}`)
      navigate('/notifications')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل الحذف')
    }
  }

  if (loading) return <PageSkeleton />
  if (!notification) return <div className="text-danger">{error || 'الإشعار غير موجود.'}</div>

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الإشعار</h1>
        <Button variant="secondary" onClick={() => navigate('/notifications')}>العودة للإشعارات</Button>
      </header>

      {error && <div className="mt-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <Card className="mt-4">
        <CardHeader>
          <div className="flex items-center gap-2">
            <CardTitle>{notification.title}</CardTitle>
            {notification.read_at
              ? <Badge variant="success">مقروء</Badge>
              : <Badge variant="primary">جديد</Badge>}
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-3 text-sm text-foreground">
            <div>
              <span className="font-medium">النوع:</span>{' '}
              <Badge variant="default">{notification.type ?? 'info'}</Badge>
            </div>
            {notification.message && (
              <div className="rounded-lg bg-surface p-3 leading-relaxed">{notification.message}</div>
            )}
            <div>
              <span className="font-medium">التاريخ:</span>{' '}
              {notification.created_at ? new Date(notification.created_at).toLocaleString('ar-LY') : '-'}
            </div>
            <div className="flex items-center gap-2 pt-2">
              {notification.link && (
                <Button variant="primary" size="sm" onClick={() => navigate(notification.link)}>
                  فتح الصفحة المرتبطة
                </Button>
              )}
              <Button variant="danger" size="sm" onClick={() => setConfirmOpen(true)}>حذف الإشعار</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmOpen}
        message="هل تريد حذف هذا الإشعار؟ لا يمكن التراجع عن هذا الإجراء."
        onCancel={() => setConfirmOpen(false)}
        onConfirm={remove}
      />
    </>
  )
}
