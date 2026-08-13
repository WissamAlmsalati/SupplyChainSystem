import { NavLink } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const links = [
  { to: '/', label: 'الرئيسية' },
  { to: '/orders', label: 'الطلبات' },
  { to: '/branches', label: 'فروعي' },
  { to: '/profile', label: 'الملف الشخصي' },
]

export default function Nav() {
  const { user, logout } = useAuth()

  return (
    <aside className="flex h-auto flex-col border-b border-border bg-surface lg:h-full lg:w-64 lg:flex-shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-l">
      <div className="p-5">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">
            م
          </div>
          <div>
            <div className="font-bold text-foreground">تطبيق المقهى</div>
            <div className="text-xs text-muted">{user?.cafe?.name ?? user?.name}</div>
          </div>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 pb-3 lg:overflow-visible">
        <ul className="space-y-1">
          {links.map((link) => (
            <li key={link.to}>
              <NavLink
                to={link.to}
                className={({ isActive }) =>
                  `flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary text-white'
                      : 'text-muted hover:bg-background hover:text-foreground'
                  }`
                }
              >
                {link.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      <div className="border-t border-border p-4">
        <div className="mb-1 font-semibold text-foreground">{user?.name}</div>
        <div className="mb-3 text-xs text-muted break-words">{user?.email}</div>
        <button
          onClick={logout}
          className="w-full rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-surface"
        >
          تسجيل الخروج
        </button>
      </div>
    </aside>
  )
}
