import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import Button from '../components/ui/Button'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'
import { PageSkeleton } from '../components/ui/Skeleton'

const operationLabels = {
  view: 'عرض',
  create: 'إضافة',
  edit: 'تعديل',
  delete: 'حذف',
  manage: 'إدارة',
  assign: 'تعيين',
}

const moduleLabels = {
  customer_branches: 'العناوين',
  categories: 'التصنيفات',
  products: 'المنتجات',
  inventory: 'المخزون',
  orders: 'الطلبات',
  warehouses: 'المستودعات',
  delivery_zones: 'مناطق التوصيل',
  users: 'المستخدمين',
  user_types: 'أنواع المستخدمين',
  permissions: 'الصلاحيات',
  order: 'الطلبات',
  zone: 'المناطق',
}

function moduleName(module) {
  return moduleLabels[module] || module
}

function opName(op) {
  return operationLabels[op] || op
}

function groupPermissions(permissions) {
  const groups = {}
  permissions.forEach((p) => {
    const parts = p.code.split('_')
    const op = parts.pop()?.toLowerCase()
    const module = parts.join('_').toLowerCase()
    if (!groups[module]) groups[module] = []
    groups[module].push({ ...p, op })
  })
  return groups
}

export default function UserTypeDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [userType, setUserType] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(`/user-types/${id}`)
      setUserType(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات الدور')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const grouped = userType?.permissions ? groupPermissions(userType.permissions) : {}

  const userColumns = [
    { key: 'name', label: 'الاسم' },
    { key: 'email', label: 'البريد الإلكتروني' },
    { key: 'mobile_number', label: 'الجوال' },
    {
      key: 'addresses',
      label: 'العناوين',
      render: (r) => (
        <Button variant="secondary" size="sm" onClick={() => navigate(`/addresses?user_id=${r.id}`)}>العناوين</Button>
      ),
    },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  if (loading) return <PageSkeleton />
  if (!userType) return <div className="text-danger">{error || 'الدور غير موجود.'}</div>

  const users = userType.app_users ?? []

  return (
    <>
      <header className="flex flex-col gap-4 rounded-lg border-b border-black bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل الدور</h1>
          <p className="mt-1 text-muted">{userType.name || 'دور'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/user-types')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات الدور</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">الاسم:</span> {userType.name ?? '-'}</div>
              <div><span className="font-medium">عدد الصلاحيات:</span> {userType.permissions?.length ?? 0}</div>
              <div><span className="font-medium">عدد المستخدمين:</span> {users.length}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>الصلاحيات</CardTitle>
          </CardHeader>
          <CardContent>
            {Object.entries(grouped).length === 0 ? (
              <div className="text-sm text-muted">لا توجد صلاحيات مسجلة لهذا الدور.</div>
            ) : (
              <div className="space-y-4">
                {Object.entries(grouped).map(([module, modulePerms]) => (
                  <div key={module}>
                    <div className="mb-1.5 font-semibold text-foreground">{moduleName(module)}</div>
                    <div className="flex flex-wrap gap-1">
                      {modulePerms.map((p) => (
                        <Badge key={p.id} variant="primary">{opName(p.op)}</Badge>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>المستخدمين تحت هذا الدور</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={userColumns}
            rows={users}
            loading={false}
            emptyText="لا يوجد مستخدمون مسندون لهذا الدور."
            onRowClick={(row) => navigate(`/users/${row.id}`)}
          />
        </CardContent>
      </Card>
    </>
  )
}
