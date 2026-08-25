import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

const MailIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect width="20" height="16" x="2" y="4" rx="2" />
    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
  </svg>
)

const LockIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
  </svg>
)

const LogoIcon = () => (
  <img src="/favicon.svg" alt="logo" className="h-8 w-8 object-contain" />
)

export default function Login() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [email, setEmail] = useState('admin@example.com')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(email, password)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تسجيل الدخول')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen w-full">
      {/* Brand side */}
      <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-primary to-[#115e57] p-12 text-primary-foreground lg:flex">
        <div className="relative z-10">
          <div className="mb-8 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-sm ring-1 ring-white/20">
            <LogoIcon />
          </div>
          <h1 className="text-4xl font-extrabold leading-tight">
            الساحل لمستلزمات المقاهي
          </h1>
          <p className="mt-5 max-w-md text-lg leading-relaxed text-white/85">
            نظام متكامل لإدارة المقاهي، المخزون، الطلبات، الفروع، ومناطق التوصيل — كل شي من مكان واحد.
          </p>
        </div>

        <div className="relative z-10 flex items-center gap-4 text-sm text-white/70">
          <span className="h-px w-10 bg-white/30" />
          <span>لوحة التحكم</span>
        </div>

        {/* Decorative circles */}
        <div className="absolute -bottom-28 -end-28 h-[28rem] w-[28rem] rounded-full bg-white/5" />
        <div className="absolute -top-24 -start-24 h-80 w-80 rounded-full bg-white/5" />
      </div>

      {/* Form side */}
      <div className="flex w-full flex-col items-center justify-center bg-background px-6 py-12 lg:w-1/2">
        <div className="w-full max-w-md text-center">
          <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-md shadow-primary/20">
            <LogoIcon />
          </div>
          <h2 className="text-3xl font-extrabold text-foreground">تسجيل الدخول</h2>
          <p className="mt-2 text-sm text-muted">أدخل بيانات حسابك للمتابعة</p>

          <form onSubmit={handleSubmit} className="mt-8 space-y-5 text-start">
            {error && (
              <div className="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
                {error}
              </div>
            )}
            <Input
              label="البريد الإلكتروني"
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="admin@example.com"
              icon={<MailIcon />}
              required
            />
            <Input
              label="كلمة المرور"
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              icon={<LockIcon />}
              required
            />
            <Button
              type="submit"
              variant="primary"
              size="lg"
              disabled={loading}
              className="w-full shadow-md shadow-primary/20"
            >
              {loading ? 'جاري الدخول...' : 'تسجيل الدخول'}
            </Button>
          </form>
        </div>

        <p className="mt-8 text-center text-xs text-muted">
          © {new Date().getFullYear()} الساحل لمستلزمات المقاهي — جميع الحقوق محفوظة
        </p>
      </div>
    </div>
  )
}
