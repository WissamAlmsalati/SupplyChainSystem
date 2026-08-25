import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import client from '../api/client'
import MapPicker from '../components/MapPicker'

export default function Register() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    cafe_name: '',
    phone_number: '',
    email: '',
    password: '',
    address: '',
    latitude: '',
    longitude: '',
  })
  const [logo, setLogo] = useState(null)
  const [logoPreview, setLogoPreview] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [mapOpen, setMapOpen] = useState(false)

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleLogoChange = (e) => {
    const file = e.target.files[0]
    setLogo(file || null)
    setLogoPreview(file ? URL.createObjectURL(file) : null)
  }

  const handleMapSelect = ({ lat, lng }) => {
    setForm((prev) => ({
      ...prev,
      latitude: String(lat.toFixed(5)),
      longitude: String(lng.toFixed(5)),
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    setLoading(true)

    const data = new FormData()
    data.append('cafe_name', form.cafe_name)
    data.append('phone_number', form.phone_number)
    data.append('password', form.password)
    data.append('address', form.address)
    if (form.email) data.append('email', form.email)
    if (form.latitude) data.append('latitude', form.latitude)
    if (form.longitude) data.append('longitude', form.longitude)
    if (logo) data.append('logo', logo)

    try {
      await client.post('/cafe/register', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setSuccess('تم إرسال طلب التسجيل بنجاح، سيتم مراجعته والتواصل معك قريبًا.')
      setTimeout(() => navigate('/login'), 3000)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إرسال طلب التسجيل')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4 py-8">
      <div className="w-full max-w-lg rounded-2xl border border-border bg-surface p-8 shadow-lg">
        <div className="mb-6 text-center">
          <img
            src="/logo.svg"
            alt="الساحل لمستلزمات المقاهي"
            className="mx-auto mb-4 h-20 w-auto"
          />
          <h1 className="text-2xl font-bold text-foreground">تسجيل مقهى جديد</h1>
          <p className="mt-1 text-sm text-muted">املأ البيانات التالية وسنراجع طلبك في أقرب وقت</p>
        </div>

        {error && (
          <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
            {error}
          </div>
        )}

        {success && (
          <div className="mb-4 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">
            {success}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">اسم المقهى</label>
            <input
              type="text"
              name="cafe_name"
              required
              value={form.cafe_name}
              onChange={handleChange}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="مقهى الساحل"
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">رقم الهاتف</label>
              <input
                type="tel"
                name="phone_number"
                required
                value={form.phone_number}
                onChange={handleChange}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                placeholder="0912345678"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-muted">البريد الإلكتروني (اختياري)</label>
              <input
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                placeholder="cafe@example.com"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">كلمة المرور</label>
            <input
              type="password"
              name="password"
              required
              minLength={6}
              value={form.password}
              onChange={handleChange}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="••••••••"
            />
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">العنوان</label>
            <input
              type="text"
              name="address"
              required
              value={form.address}
              onChange={handleChange}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="طرابلس، ليبيا"
            />
          </div>

          <div>
            <div className="mb-1.5 flex items-center justify-between">
              <label className="text-sm font-medium text-muted">موقع المقهى (اختياري)</label>
              <button
                type="button"
                onClick={() => setMapOpen(true)}
                className="text-sm font-medium text-primary hover:underline"
              >
                اختيار من الخريطة
              </button>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <input
                type="number"
                step="any"
                name="latitude"
                value={form.latitude}
                onChange={handleChange}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                placeholder="خط العرض"
              />
              <input
                type="number"
                step="any"
                name="longitude"
                value={form.longitude}
                onChange={handleChange}
                className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                placeholder="خط الطول"
              />
            </div>
            <p className="mt-1 text-xs text-muted">تقدر تسجّل المقهى الآن وتحدد الموقع لاحقًا عند إضافة الفرع الرئيسي.</p>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">شعار المقهى (اختياري)</label>
            <input
              type="file"
              accept="image/*"
              onChange={handleLogoChange}
              className="block w-full text-sm text-foreground file:mr-4 file:rounded file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
            />
            {logoPreview && (
              <img src={logoPreview} alt="شعار المقهى" className="mt-2 h-20 w-20 rounded object-cover" />
            )}
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
          >
            {loading ? 'جاري الإرسال...' : 'إرسال طلب التسجيل'}
          </button>
        </form>

        <div className="mt-6 text-center text-sm text-muted">
          لديك حساب؟{' '}
          <Link to="/login" className="font-medium text-primary hover:underline">
            تسجيل الدخول
          </Link>
        </div>
      </div>

      <MapPicker
        open={mapOpen}
        onClose={() => setMapOpen(false)}
        onSelect={handleMapSelect}
        initial={
          form.latitude && form.longitude
            ? { lat: Number(form.latitude), lng: Number(form.longitude) }
            : undefined
        }
        zones={[]}
      />
    </div>
  )
}
