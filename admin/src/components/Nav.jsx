import { NavLink } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Button from './ui/Button'

const links = [
  { to: '/', label: 'الرئيسية', permission: 'DASHBOARD_VIEW' },
  { to: '/orders', label: 'الطلبات', permission: 'ORDERS_VIEW' },
  { to: '/products', label: 'المنتجات', permission: 'PRODUCTS_VIEW' },
  { to: '/inventory', label: 'المخزون', permission: 'INVENTORY_VIEW' },

  { to: '/warehouses', label: 'المستودعات', permission: 'WAREHOUSES_VIEW' },
  { to: '/cafe-branches', label: 'فروع المقاهي', permission: 'CAFE_BRANCHES_VIEW' },
  { to: '/delivery-zones', label: 'مناطق التوصيل', permission: 'DELIVERY_ZONES_VIEW' },
  { to: '/map', label: 'الخريطة' },
  { to: '/categories', label: 'التصنيفات', permission: 'CATEGORIES_VIEW' },
  { to: '/cafes', label: 'المقاهي', permission: 'CAFES_VIEW' },
  { to: '/users', label: 'المستخدمين', permission: 'USERS_VIEW' },
  { to: '/user-types', label: 'الأدوار والصلاحيات', permission: 'USER_TYPES_VIEW' },
  { to: '/activity-logs', label: 'سجل النشاطات', permission: 'ACTIVITY_LOGS_VIEW' },
]

export default function Nav() {
  const { user, logout, hasAnyPermission } = useAuth()

  const visibleLinks = links.filter((link) => {
    if (link.superAdminOnly) return user?.user_type?.name === 'super_admin'
    if (link.permission) return hasAnyPermission([link.permission])
    return true
  })

  return (
    <aside className="flex h-auto flex-col border-b border-border bg-surface lg:h-full lg:w-64 lg:flex-shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-l">
      <div className="p-5">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M3 3v16a2 2 0 0 0 2 2h16" />
              <path d="m19 9-5 5-4-4-3 3" />
            </svg>
          </div>
          <div>
            <div className="font-bold text-foreground">سلسلة الإمداد</div>
            <div className="text-xs text-muted">لوحة البائع</div>
          </div>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 pb-3 lg:overflow-visible">
        <ul className="space-y-1">
          {visibleLinks.map((link) => (
            <li key={link.to}>
              <NavLink
                to={link.to}
                className={({ isActive }) =>
                  `flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary !text-white'
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
        <Button variant="secondary" className="w-full" onClick={logout}>
          تسجيل الخروج
        </Button>
      </div>
    </aside>
  )
}
