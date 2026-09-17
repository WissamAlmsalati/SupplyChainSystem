import { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Warehouse,
  MapPin,
  Map,
  Tags,
  Users,
  Truck,
  ShieldCheck,
  ClipboardList,
  Bell,
  Star,
  Megaphone,
  PanelLeftClose,
  PanelLeftOpen,
  LogOut,
  Wallet,
  BanknoteArrowUp,
  HandCoins,
  LayoutList,
} from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import Button from './ui/Button'

const icons = {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Warehouse,
  MapPin,
  Map,
  Tags,
  Users,
  Truck,
  ShieldCheck,
  ClipboardList,
  Bell,
  Star,
  Megaphone,
  Wallet,
  BanknoteArrowUp,
  HandCoins,
  LayoutList,
}

const groups = [
  {
    title: 'العمليات',
    links: [
      { to: '/', label: 'الرئيسية', icon: 'LayoutDashboard', permission: 'DASHBOARD_VIEW' },
      { to: '/orders', label: 'الطلبات', icon: 'ShoppingCart', permission: 'ORDERS_VIEW' },
      { to: '/products', label: 'المنتجات', icon: 'Package', permission: 'PRODUCTS_VIEW' },
      { to: '/inventory', label: 'المخزون', icon: 'Warehouse', permission: 'INVENTORY_VIEW' },
    ],
  },
  {
    title: 'المالية',
    links: [
      { to: '/wallets', label: 'المحافظ والسيولة', icon: 'Wallet', permission: 'WALLETS_VIEW' },
      { to: '/wallet-topups', label: 'طلبات الشحن', icon: 'BanknoteArrowUp', permission: 'WALLET_TOPUPS_VIEW' },
      { to: '/custody', label: 'عهد المناديب', icon: 'HandCoins', permission: 'CUSTODY_VIEW' },
    ],
  },
  {
    title: 'اللوجستيات',
    links: [
      { to: '/warehouses', label: 'المستودعات', icon: 'Warehouse', permission: 'WAREHOUSES_VIEW' },
      { to: '/addresses', label: 'العناوين', icon: 'MapPin', permission: 'CUSTOMER_BRANCHES_VIEW' },
      { to: '/delivery-zones', label: 'مناطق التوصيل', icon: 'Map', permission: 'DELIVERY_ZONES_VIEW' },
      { to: '/map', label: 'الخريطة', icon: 'Map' },
    ],
  },
  {
    title: 'الكتالوج',
    links: [
      { to: '/categories', label: 'التصنيفات', icon: 'Tags', permission: 'CATEGORIES_VIEW' },
      { to: '/featured-sections', label: 'الأقسام المميزة', icon: 'LayoutList', permission: 'FEATURED_SECTIONS_VIEW' },
    ],
  },
  {
    title: 'الإدارة',
    links: [
      {
        to: '/users',
        label: 'الإدارة',
        icon: 'Users',
        permission: 'USERS_VIEW',
      },
      {
        to: '/customers',
        label: 'المقاهي',
        icon: 'Users',
        permission: 'USERS_VIEW',
      },
      { to: '/delegates', label: 'المناديب', icon: 'Truck', permission: 'DELEGATES_VIEW' },
      { to: '/user-types', label: 'الأدوار والصلاحيات', icon: 'ShieldCheck', permission: 'USER_TYPES_VIEW' },
    ],
  },
  {
    title: 'النظام',
    links: [
      { to: '/activity-logs', label: 'سجل النشاطات', icon: 'ClipboardList', permission: 'ACTIVITY_LOGS_VIEW' },
      { to: '/notifications', label: 'الإشعارات', icon: 'Bell' },
      { to: '/promos', label: 'البروموهات', icon: 'Megaphone' },
      { to: '/premium-features', label: 'الميزات المميزة', icon: 'Star' },
    ],
  },
]

export default function Nav() {
  const { user, logout, hasAnyPermission } = useAuth()
  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false
    return window.localStorage.getItem('nav-collapsed') === 'true'
  })

  useEffect(() => {
    window.localStorage.setItem('nav-collapsed', String(collapsed))
  }, [collapsed])

  const visibleGroups = groups
    .map((group) => ({
      ...group,
      links: group.links.filter((link) => {
        if (link.superAdminOnly) return user?.user_type?.name === 'super_admin'
        if (link.permission) return hasAnyPermission([link.permission])
        return true
      }),
    }))
    .filter((group) => group.links.length > 0)

  return (
    <aside
      className={`flex h-auto flex-col border-b border-border bg-surface transition-all duration-200 lg:h-full lg:flex-shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-l ${
        collapsed ? 'lg:w-20' : 'lg:w-64'
      }`}
    >
      <div className={`flex items-center p-5 ${collapsed ? 'lg:flex-col lg:justify-center lg:gap-2' : 'justify-between'}`}>
        <div className="flex items-center gap-3">
          <img
            src="/favicon.svg"
            alt="logo"
            className="h-10 w-10 rounded-lg object-contain"
          />
          {!collapsed && (
            <div>
              <div className="font-bold text-foreground">الساحل</div>
              <div className="text-xs text-muted">لمستلزمات المقاهي</div>
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={() => setCollapsed((c) => !c)}
          className="rounded-md p-2 text-muted hover:bg-background hover:text-foreground"
          title={collapsed ? 'توسيع' : 'تصغير'}
        >
          {collapsed ? <PanelLeftOpen className="h-5 w-5" /> : <PanelLeftClose className="h-5 w-5" />}
        </button>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 pb-3 lg:overflow-visible">
        <div className="space-y-5">
          {visibleGroups.map((group) => (
            <div key={group.title}>
              {!collapsed && (
                <div className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-muted">
                  {group.title}
                </div>
              )}
              <ul className="space-y-1">
                {group.links.map((link) => {
                  const Icon = link.icon ? icons[link.icon] : null
                  const [linkPath] = link.to.split('?')
                  return (
                    <li key={link.to}>
                      <NavLink
                        to={link.to}
                        title={collapsed ? link.label : undefined}
                        end={false}
                        isActive={(_, location) => {
                          if (link.isActive) return link.isActive(location)
                          return location.pathname === linkPath
                        }}
                        className={({ isActive }) =>
                          `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                            collapsed ? 'lg:justify-center' : ''
                          } ${
                            isActive
                              ? 'bg-primary !text-white'
                              : 'text-muted hover:bg-background hover:text-foreground'
                          }`
                        }
                      >
                        {Icon && <Icon className="h-5 w-5 flex-shrink-0" />}
                        {!collapsed && link.label}
                      </NavLink>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}
        </div>
      </nav>

      <div className="border-t border-border p-4">
        {!collapsed && (
          <>
            <div className="mb-1 font-semibold text-foreground">{user?.name}</div>
            <div className="mb-3 text-xs text-muted break-words">{user?.email}</div>
          </>
        )}
        <Button
          variant="secondary"
          className={`${collapsed ? 'lg:px-2' : 'w-full'}`}
          onClick={logout}
          title="تسجيل الخروج"
        >
          {collapsed ? <LogOut className="h-5 w-5" /> : 'تسجيل الخروج'}
        </Button>
      </div>
    </aside>
  )
}
