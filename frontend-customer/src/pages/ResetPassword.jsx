import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import client from '../api/client'

export default function ResetPassword() {
  const location = useLocation()
  const navigate = useNavigate()
  const { token, mobileNumber } = location.state || {}
  const [otp, setOtp] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    if (!token) {
      setError('انتهت الجلسة، ابدأ من جديد')
      return
    }
    setLoading(true)
    try {
      const res = await client.post('/customer/reset-password', {
        token,
        otp,
        password,
        password_confirmation: passwordConfirmation,
      })
      setSuccess(res.data?.message || 'تم إعادة تعيين كلمة المرور')
      setTimeout(() => navigate('/login'), 1500)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إعادة تعيين كلمة المرور')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-lg">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-foreground">إعادة تعيين كلمة المرور</h1>
          <p className="mt-1 text-sm text-muted">أدخل رمز التحقق وكلمة المرور الجديدة</p>
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
            <label className="mb-1.5 block text-sm font-medium text-muted">رمز التحقق (OTP)</label>
            <input
              type="text"
              required
              value={otp}
              onChange={(e) => setOtp(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="123456"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">كلمة المرور الجديدة</label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="••••••••"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-muted">تأكيد كلمة المرور</label>
            <input
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="••••••••"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
          >
            {loading ? 'جاري الحفظ...' : 'حفظ كلمة المرور'}
          </button>
        </form>

        <div className="mt-6 text-center text-sm text-muted">
          <Link to="/login" className="font-medium text-primary hover:underline">
            العودة لتسجيل الدخول
          </Link>
        </div>
      </div>
    </div>
  )
}
