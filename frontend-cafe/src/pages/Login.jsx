import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Login() {
  const [phoneNumber, setPhoneNumber] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const user = await login(phoneNumber, password)
      if (user?.user_type?.name === 'delegate') {
        navigate('/delegate')
        return
      }
      if (user?.user_type?.name !== 'cafe') {
        setError('هذا التطبيق مخصص لمديري المقاهي والمناديب فقط.')
        return
      }
      navigate('/')
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تسجيل الدخول')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-lg">
        <div className="mb-6 text-center">
          <img
            src="/logo.svg"
            alt="الساحل لمستلزمات المقاهي"
            className="mx-auto mb-4 h-20 w-auto"
          />
          <h1 className="text-2xl font-bold text-foreground">الساحل لمستلزمات المقاهي</h1>
          <p className="mt-1 text-sm text-muted">تسجيل الدخول إلى حساب مقهاك أو حساب المندوب</p>
        </div>

        {error && (
          <div className="mb-4 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">رقم الهاتف</label>
            <input
              type="tel"
              required
              value={phoneNumber}
              onChange={(e) => setPhoneNumber(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="0912345678"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">كلمة المرور</label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="••••••••"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
          >
            {loading ? 'جاري الدخول...' : 'دخول'}
          </button>
        </form>

        <div className="mt-6 text-center text-sm text-muted">
          ما عندك حساب؟{' '}
          <Link to="/register" className="font-medium text-primary hover:underline">
            سجّل مقهاك
          </Link>
        </div>
      </div>
    </div>
  )
}
