import { useEffect, useState } from 'react'
import { NavLink, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { usePremiumFeatureActive } from '../hooks/usePremiumFeatureActive'
import client from '../api/client'

const delegateLinks = [
  { to: '/delegate', label: 'لوحة المندوب' },
  { to: '/orders', label: 'طلباتي' },
  { to: '/profile', label: 'الملف الشخصي' },
]

export default function Nav({ mobileOpen, onMobileClose, showDesktop = true }) {
  const { user, logout } = useAuth()
  const { itemCount } = useCart()
  const isDelegate = user?.user_type?.name === 'delegate'
  const branchesFeature = usePremiumFeatureActive('cafe_branches')
  const [categories, setCategories] = useState([])

  useEffect(() => {
    if (isDelegate) return
    client.get('/cafe/categories').then((res) => {
      setCategories(res.data?.data ?? [])
    })
  }, [isDelegate])

  const mainLinks = isDelegate
    ? delegateLinks
    : [
        { to: '/', label: 'الرئيسية' },
        { to: '/products', label: 'كل المنتجات' },
        { to: '/cart', label: 'السلة', badge: itemCount },
        { to: '/orders', label: 'طلباتي' },
        ...(branchesFeature ? [{ to: '/branches', label: 'فروعي' }] : []),
        { to: '/profile', label: 'الملف الشخصي' },
      ]

  const navContent = (
    <>
      <div className="p-5 lg:hidden">
        <div className="flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2" onClick={onMobileClose}>
            <img src="/logo.svg" alt="الساحل" className="h-10 w-auto" />
            <span className="font-bold text-foreground">الساحل</span>
          </Link>
          <button onClick={onMobileClose} className="rounded-lg p-2 text-muted hover:bg-background">
            ✕
          </button>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 pb-3">
        <ul className="space-y-1">
          {mainLinks.map((link) => (
            <li key={link.to}>
              <NavLink
                to={link.to}
                onClick={onMobileClose}
                className={({ isActive }) =>
                  `flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary text-white'
                      : 'text-muted hover:bg-background hover:text-foreground'
                  }`
                }
              >
                <span>{link.label}</span>
                {link.badge > 0 && (
                  <span className="rounded-full bg-danger px-2 py-0.5 text-xs font-bold text-white">
                    {link.badge}
                  </span>
                )}
              </NavLink>
            </li>
          ))}
        </ul>

        {!isDelegate && categories.length > 0 && (
          <>
            <div className="mb-2 mt-6 px-3 text-xs font-semibold uppercase tracking-wider text-muted">
              التصنيفات
            </div>
            <ul className="space-y-1">
              {categories.map((c) => (
                <li key={c.id}>
                  <NavLink
                    to={`/products?category=${c.id}`}
                    onClick={onMobileClose}
                    className={({ isActive }) =>
                      `block rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                        isActive
                          ? 'bg-primary text-white'
                          : 'text-muted hover:bg-background hover:text-foreground'
                      }`
                    }
                  >
                    {c.name}
                  </NavLink>
                </li>
              ))}
            </ul>
          </>
        )}
      </nav>

      <div className="border-t border-border p-4">
        <div className="mb-1 font-semibold text-foreground">{user?.name}</div>
        <div className="mb-3 text-xs text-muted break-words">{user?.email || user?.mobile_number}</div>
        <button
          onClick={logout}
          className="w-full rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-surface"
        >
          تسجيل الخروج
        </button>
      </div>
    </>
  )

  return (
    <>
      {/* Desktop sidebar — shown only for delegates */}
      {showDesktop && (
        <aside className="hidden h-full w-64 flex-shrink-0 flex-col border-l border-border bg-surface lg:flex lg:overflow-y-auto">
          <div className="p-5">
            <Link to="/" className="flex items-center gap-2">
              <img src="/logo.svg" alt="الساحل" className="h-10 w-auto" />
              <div>
                <div className="font-bold text-foreground">الساحل</div>
                <div className="text-xs text-muted">لمستلزمات المقاهي</div>
              </div>
            </Link>
          </div>
          {navContent}
        </aside>
      )}

      {/* Mobile drawer */}
      {mobileOpen && (
        <>
          <div
            className="fixed inset-0 z-40 bg-black/40 lg:hidden"
            onClick={onMobileClose}
          />
          <aside className="fixed inset-y-0 start-0 z-50 flex w-64 flex-col border-l border-border bg-surface lg:hidden">
            {navContent}
          </aside>
        </>
      )}
    </>
  )
}
