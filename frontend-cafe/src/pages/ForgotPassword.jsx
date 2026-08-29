import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import client from '../api/client'

export default function ForgotPassword() {
  const [mobileNumber, setMobileNumber] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const navigate = useNavigate()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSuccess('')
    setLoading(true)
    try {
      const res = await client.post('/cafe/forgot-password', { mobile_number: mobileNumber })
      setSuccess(res.data?.message || 'تم إرسال رمز التحقق')
      setTimeout(() => {
        navigate('/reset-password', { state: { token: res.data?.token, mobileNumber } })
      }, 1000)
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إرسال رمز التحقق')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-lg">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-foreground">نسيت كلمة المرور</h1>
          <p className="mt-1 text-sm text-muted">أدخل رقم هاتفك لإرسال رمز التحقق</p>
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
            <label className="mb-1.5 block text-sm font-medium text-muted">رقم الهاتف</label>
            <input
              type="tel"
              required
              value={mobileNumber}
              onChange={(e) => setMobileNumber(e.target.value)}
              className="w-full rounded-lg border border-border-strong bg-background px-4 py-2.5 text-foreground outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
              placeholder="0912345678"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
          >
            {loading ? 'جاري الإرسال...' : 'إرسال رمز التحقق'}
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
