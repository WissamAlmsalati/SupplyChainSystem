import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useModulePermission } from '../hooks/usePermission'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/Card'
import DataTable from '../components/DataTable'
import Badge from '../components/ui/Badge'

export default function DeliveryZoneDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [zone, setZone] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [form, setForm] = useState({ name: '', delivery_price: '', is_active: true })
  const { canEdit: canEditZone } = useModulePermission('DELIVERY_ZONES')
  const { canEdit: canEditBranch } = useModulePermission('CAFE_BRANCHES')
  const [addingBranch, setAddingBranch] = useState(false)
  const [availableBranches, setAvailableBranches] = useState([])
  const [branchesLoading, setBranchesLoading] = useState(false)
  const [selectedBranchId, setSelectedBranchId] = useState('')

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError('')
      try {
        const res = await client.get(`/delivery-zones/${id}`)
        const data = res.data?.data ?? res.data
        setZone(data)
        setForm({
          name: data.name || '',
          delivery_price: String(data.delivery_price ?? ''),
          is_active: data.is_active,
        })
      } catch (err) {
        setError(err.response?.data?.message || 'فشل تحميل بيانات المنطقة')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [id])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      await client.put(`/delivery-zones/${id}`, {
        ...form,
        delivery_price: Number(form.delivery_price),
      })
      const res = await client.get(`/delivery-zones/${id}`)
      setZone(res.data?.data ?? res.data)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ التعديلات')
    } finally {
      setSaving(false)
    }
  }

  const loadAvailableBranches = async () => {
    if (!zone) return
    setBranchesLoading(true)
    try {
      const res = await client.get('/cafe-branches?per_page=1000')
      const list = res.data?.data ?? res.data ?? []
      setAvailableBranches(
        list.filter((b) => Number(b.delivery_zone_id) !== Number(zone.id))
      )
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الفروع')
    } finally {
      setBranchesLoading(false)
    }
  }

  const startAddingBranch = () => {
    setAddingBranch(true)
    setSelectedBranchId('')
    loadAvailableBranches()
  }

  const cancelAddingBranch = () => {
    setAddingBranch(false)
    setSelectedBranchId('')
  }

  const handleAddBranch = async () => {
    if (!selectedBranchId) return
    setSaving(true)
    setError('')
    try {
      await client.put(`/cafe-branches/${selectedBranchId}`, {
        delivery_zone_id: zone.id,
      })
      const res = await client.get(`/delivery-zones/${id}`)
      setZone(res.data?.data ?? res.data)
      setAddingBranch(false)
      setSelectedBranchId('')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إضافة الفرع للمنطقة')
    } finally {
      setSaving(false)
    }
  }

  const branchColumns = [
    { key: 'name', label: 'الاسم' },
    { key: 'cafe', label: 'المقهى', render: (r) => r.cafe?.name ?? '-' },
    { key: 'city', label: 'المدينة' },
    { key: 'street', label: 'الشارع' },
    {
      key: 'is_active',
      label: 'الحالة',
      render: (r) => <Badge variant={r.is_active ? 'success' : 'default'}>{r.is_active ? 'نشط' : 'غير نشط'}</Badge>,
    },
  ]

  if (loading) return <div className="text-muted">جاري التحميل...</div>
  if (!zone) return <div className="text-danger">{error || 'المنطقة غير موجودة.'}</div>

  return (
    <>
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-extrabold text-foreground">تفاصيل منطقة التوصيل</h1>
          <p className="mt-1 text-muted">{zone.name || 'منطقة توصيل'}</p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/delivery-zones')}>العودة للقائمة</Button>
      </header>

      {error && <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">{error}</div>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>بيانات المنطقة</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">

              <Input
                label="الاسم"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
              <Input
                label="سعر التوصيل (د.ل)"
                type="number"
                step="0.01"
                min="0"
                value={form.delivery_price}
                onChange={(e) => setForm({ ...form, delivery_price: e.target.value })}
                required
              />
              <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded border-border-strong text-primary focus:ring-primary"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                نشطة
              </label>
              {canEditZone && (
                <div className="flex items-center justify-end gap-2">
                  <Button type="submit" variant="primary" disabled={saving}>
                    {saving ? 'جاري الحفظ...' : 'حفظ التعديلات'}
                  </Button>
                </div>
              )}
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>الموقع</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2 text-sm text-foreground">
              <div><span className="font-medium">خط العرض:</span> {zone.latitude ?? '-'}</div>
              <div><span className="font-medium">خط الطول:</span> {zone.longitude ?? '-'}</div>
              <div><span className="font-medium">الحالة:</span> {zone.is_active ? 'نشطة' : 'غير نشطة'}</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <CardTitle>فروع المقاهي داخل المنطقة</CardTitle>
            {!addingBranch && canEditBranch && (
              <Button variant="primary" onClick={startAddingBranch}>
                إضافة فرع يدوياً
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent>
          {addingBranch && (
            <div className="mb-4 grid gap-3 rounded-lg border border-border bg-surface p-4 sm:grid-cols-[1fr_auto_auto]">
              <select
                className="w-full rounded-md border border-border-strong bg-background px-3.5 py-2 text-foreground shadow-sm focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
                value={selectedBranchId}
                disabled={branchesLoading || saving}
                onChange={(e) => setSelectedBranchId(e.target.value)}
              >
                <option value="">
                  {branchesLoading ? 'جاري التحميل...' : 'اختر فرعاً'}
                </option>
                {availableBranches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name} - {b.cafe?.name ?? '-'} ({b.city ?? 'بدون مدينة'})
                  </option>
                ))}
              </select>
              <Button
                variant="primary"
                disabled={!selectedBranchId || saving}
                onClick={handleAddBranch}
              >
                {saving ? 'جاري الحفظ...' : 'إضافة'}
              </Button>
              <Button variant="secondary" onClick={cancelAddingBranch}>
                إلغاء
              </Button>
            </div>
          )}
          <DataTable
            columns={branchColumns}
            rows={zone.cafe_branches || []}
            loading={false}
            emptyText="لا توجد فروع في هذه المنطقة."
          />
        </CardContent>
      </Card>
    </>
  )
}
