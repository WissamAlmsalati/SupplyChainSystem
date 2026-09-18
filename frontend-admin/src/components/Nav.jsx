import { useEffect, useMemo, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import {
  LayoutDashboard, ShoppingCart, Package, Warehouse, MapPin, Map, Tags, Users, Truck, ShieldCheck,
  ClipboardList, Bell, Star, Megaphone, PanelRightClose, PanelRightOpen, LogOut, Wallet, BanknoteArrowUp,
  HandCoins, LayoutList, BarChart3, Search, Plus, ChevronDown, X, Undo2, Gauge,
} from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import NotificationBell from './NotificationBell'

const icons = {
  LayoutDashboard, ShoppingCart, Package, Warehouse, MapPin, Map, Tags, Users, Truck, ShieldCheck,
  ClipboardList, Bell, Star, Megaphone, Wallet, BanknoteArrowUp, HandCoins, LayoutList, BarChart3, Undo2, Gauge,
}

// `count` names a key from useLiveCounts; the number shows as a badge.
export const groups = [
  {
    title: 'العمليات',
    links: [
      { to: '/', label: 'الرئيسية', icon: 'LayoutDashboard', permission: 'DASHBOARD_VIEW' },
      { to: '/orders', label: 'الطلبات', icon: 'ShoppingCart', permission: 'ORDERS_VIEW', count: 'pendingOrders' },
      { to: '/returns', label: 'المرتجعات', icon: 'Undo2', permission: 'RETURNS_VIEW' },
      { to: '/products', label: 'المنتجات', icon: 'Package', permission: 'PRODUCTS_VIEW' },
      { to: '/inventory', label: 'المخزون', icon: 'Warehouse', permission: 'INVENTORY_VIEW' },
    ],
  },
  {
    title: 'المالية',
    links: [
      { to: '/wallets', label: 'المحافظ والسيولة', icon: 'Wallet', permission: 'WALLETS_VIEW' },
      { to: '/wallet-topups', label: 'طلبات الشحن', icon: 'BanknoteArrowUp', permission: 'WALLET_TOPUPS_VIEW', count: 'pendingTopups' },
      { to: '/custody', label: 'عهد المناديب', icon: 'HandCoins', permission: 'CUSTODY_VIEW' },
      { to: '/reports', label: 'التقارير', icon: 'BarChart3', permission: 'REPORTS_VIEW' },
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
      { to: '/promos', label: 'البروموهات', icon: 'Megaphone' },
    ],
  },
  {
    title: 'الإدارة',
    links: [
      { to: '/users', label: 'الإدارة', icon: 'Users', permission: 'USERS_VIEW' },
      { to: '/customers', label: 'المقاهي', icon: 'Users', permission: 'USERS_VIEW' },
      { to: '/delegates', label: 'المناديب', icon: 'Truck', permission: 'DELEGATES_VIEW' },
      { to: '/delegate-performance', label: 'أداء المناديب', icon: 'Gauge', permission: 'REPORTS_VIEW' },
      { to: '/user-types', label: 'الأدوار والصلاحيات', icon: 'ShieldCheck', permission: 'USER_TYPES_VIEW' },
    ],
  },
  {
    title: 'النظام',
    links: [
      { to: '/activity-logs', label: 'سجل النشاطات', icon: 'ClipboardList', permission: 'ACTIVITY_LOGS_VIEW' },
      { to: '/notifications', label: 'الإشعارات', icon: 'Bell', count: 'unreadNotifications' },
      { to: '/premium-features', label: 'الميزات المميزة', icon: 'Star' },
    ],
  },
]

const roleLabels = { super_admin: 'مدير عام', admin: 'مدير', customer: 'زبون', delegate: 'مندوب' }

function readJson(key, fallback) {
  try {
    const raw = window.localStorage.getItem(key)
    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

export function useVisibleGroups() {
  const { user, hasAnyPermission } = useAuth()
  return useMemo(() => groups
    .map((group) => ({
      ...group,
      links: group.links.filter((link) => {
        if (link.superAdminOnly) return user?.user_type?.name === 'super_admin'
        if (link.permission) return hasAnyPermission([link.permission])
        return true
      }),
    }))
    .filter((group) => group.links.length > 0), [user, hasAnyPermission])
}

function Badge({ value, collapsed }) {
  if (!value) return null
  if (collapsed) return <span className="absolute top-1.5 left-1.5 h-2 w-2 rounded-full bg-ember ring-2 ring-sidebar" aria-label={String(value)} />
  return <span className="ms-auto min-w-6 rounded-full bg-ember px-1.5 text-center text-[11px] font-bold leading-5 text-sidebar tabular-nums">{value > 99 ? '99+' : value}</span>
}

/**
 * The sidebar. On desktop it is a fixed column (full or icon-only); on
 * phones the same component renders inside a slide-in drawer (see Layout).
 */
export default function Nav({ counts = {}, onSearch, onQuickOrder, drawer = false, onCloseDrawer }) {
  const { user, logout, hasPermission } = useAuth()
  const location = useLocation()
  const visibleGroups = useVisibleGroups()
  const [collapsed, setCollapsed] = useState(() => !drawer && readJson('nav-collapsed', false))
  const [closedGroups, setClosedGroups] = useState(() => readJson('nav-closed-groups', {}))

  useEffect(() => {
    if (!drawer) window.localStorage.setItem('nav-collapsed', JSON.stringify(collapsed))
  }, [collapsed, drawer])
  useEffect(() => {
    window.localStorage.setItem('nav-closed-groups', JSON.stringify(closedGroups))
  }, [closedGroups])

  const toggleGroup = (title) => setClosedGroups((s) => ({ ...s, [title]: !s[title] }))
  const iconOnly = collapsed && !drawer

  // Collapsed, an icon is all there is, so the label has to come back on hover
  // and on keyboard focus. The flyout is position: fixed because the list is a
  // scroll container and would clip anything reaching past its edge.
  const [tip, setTip] = useState(null)
  const openTip = (label) => (e) => {
    const r = e.currentTarget.getBoundingClientRect()
    setTip({ label, top: r.top + r.height / 2, left: r.left })
  }
  const closeTip = () => setTip(null)
  const tipProps = (label) => (iconOnly
    ? { onMouseEnter: openTip(label), onMouseLeave: closeTip, onFocus: openTip(label), onBlur: closeTip }
    : {})
  const todayItems = [
    { key: 'pendingOrders', label: 'طلبات جديدة', to: '/orders?status=pending', permission: 'ORDERS_VIEW' },
    { key: 'cancellationRequests', label: 'طلبات إلغاء', to: '/orders?status=cancellation_requested', permission: 'ORDERS_VIEW' },
    { key: 'pendingTopups', label: 'شحن للمراجعة', to: '/wallet-topups?status=pending', permission: 'WALLET_TOPUPS_VIEW' },
  ].filter((i) => hasPermission(i.permission))

  return (
    <aside
      className={`sidebar flex h-full flex-col bg-sidebar text-sidebar-fg transition-[width] duration-200 motion-reduce:transition-none ${
        drawer ? 'w-full' : iconOnly ? 'w-[76px]' : 'w-[268px]'
      }`}
    >
      {/* Brand + collapse */}
      <div className={`flex items-center gap-3 px-4 pt-5 pb-3 ${iconOnly ? 'flex-col' : ''}`}>
        <img src="/favicon.svg" alt="" className="h-9 w-9 rounded-lg bg-sidebar-fg object-contain p-0.5" />
        {!iconOnly && (
          <div className="min-w-0 flex-1 leading-tight">
            <div className="truncate text-[15px] font-bold">الساحل</div>
            <div className="truncate text-xs text-sidebar-faint">لمستلزمات المقاهي</div>
          </div>
        )}
        {drawer ? (
          <button type="button" onClick={onCloseDrawer} className="sb-icon-btn" aria-label="إغلاق القائمة"><X className="h-5 w-5" /></button>
        ) : (
          <button type="button" onClick={() => setCollapsed((c) => !c)} className="sb-icon-btn" title={collapsed ? 'توسيع القائمة' : 'تصغير القائمة'} aria-label={collapsed ? 'توسيع القائمة' : 'تصغير القائمة'}>
            {collapsed ? <PanelRightOpen className="h-5 w-5" /> : <PanelRightClose className="h-5 w-5" />}
          </button>
        )}
      </div>

      {/* Search + bell */}
      <div className={`flex items-center gap-2 px-3 pb-3 ${iconOnly ? 'flex-col' : ''}`}>
        <button
          type="button"
          onClick={onSearch}
          className={`sb-search ${iconOnly ? 'h-10 w-10 justify-center px-0' : 'h-10 flex-1 px-3'}`}
          title={iconOnly ? undefined : 'بحث سريع (Ctrl+K)'}
          aria-label="بحث سريع"
          {...tipProps('بحث سريع')}
        >
          <Search className="h-4 w-4 shrink-0" />
          {!iconOnly && (
            <>
              <span className="flex-1 truncate text-right text-sm">ابحث أو انتقل…</span>
              <kbd className="rounded border border-sidebar-edge px-1.5 text-[10px] text-sidebar-faint" dir="ltr">Ctrl K</kbd>
            </>
          )}
        </button>
        <NotificationBell tone="dark" />
      </div>

      {/* Today: the numbers that need a person right now */}
      {!iconOnly && todayItems.length > 0 && (
        <div className="mx-3 mb-3 grid grid-cols-3 gap-px overflow-hidden rounded-lg border border-sidebar-line bg-sidebar-line">
          {todayItems.map((i) => {
            const v = counts[i.key]
            return (
              <NavLink key={i.key} to={i.to} className="bg-sidebar px-2 py-2 text-center hover:bg-sidebar-2">
                <div className={`text-lg font-black leading-6 tabular-nums ${v > 0 ? 'text-ember' : 'text-sidebar-fg/70'}`}>{v ?? '–'}</div>
                <div className="text-[10px] leading-3 text-sidebar-faint">{i.label}</div>
              </NavLink>
            )
          })}
        </div>
      )}

      {/* Links */}
      <nav className="sb-scroll flex-1 overflow-y-auto overflow-x-hidden pb-3 ps-0 pe-0">
        {visibleGroups.map((group) => {
          const closed = !!closedGroups[group.title] && !iconOnly
          return (
            <div key={group.title} className="mb-1">
              {!iconOnly && (
                <button
                  type="button"
                  onClick={() => toggleGroup(group.title)}
                  className="flex w-full items-center gap-1 px-5 pb-1 pt-3 text-[12px] font-medium text-sidebar-faint hover:text-sidebar-fg"
                  aria-expanded={!closed}
                >
                  <span>{group.title}</span>
                  <ChevronDown className={`h-3.5 w-3.5 transition-transform motion-reduce:transition-none ${closed ? '-rotate-90' : ''}`} />
                </button>
              )}
              {iconOnly && <div className="mx-4 my-2 border-t border-sidebar-line" />}
              {!closed && (
                <ul>
                  {group.links.map((link) => {
                    const Icon = link.icon ? icons[link.icon] : null
                    const [linkPath] = link.to.split('?')
                    const isActive = location.pathname === linkPath || (linkPath !== '/' && location.pathname.startsWith(linkPath + '/'))
                    const badge = link.count ? counts[link.count] : null
                    return (
                      <li key={link.to} className="relative">
                        <NavLink
                          to={link.to}
                          aria-label={iconOnly ? link.label : undefined}
                          aria-current={isActive ? 'page' : undefined}
                          className={`sb-link ${isActive ? 'sb-active' : ''} ${iconOnly ? 'sb-link-icon' : ''}`}
                          {...tipProps(link.label)}
                        >
                          {Icon && <Icon className="h-[18px] w-[18px] shrink-0" />}
                          {!iconOnly && <span className="truncate">{link.label}</span>}
                          <Badge value={badge} collapsed={iconOnly} />
                        </NavLink>
                      </li>
                    )
                  })}
                </ul>
              )}
            </div>
          )
        })}
      </nav>

      {/* Quick action */}
      {hasPermission('ORDERS_CREATE') && (
        <div className={`px-3 pb-3 ${iconOnly ? 'flex justify-center' : ''}`}>
          <button type="button" onClick={onQuickOrder} className={`sb-primary ${iconOnly ? 'h-10 w-10 justify-center' : 'h-10 w-full px-3'}`} aria-label="طلب سريع" {...tipProps('طلب سريع')}>
            <Plus className="h-4 w-4" />
            {!iconOnly && <span>طلب سريع</span>}
          </button>
        </div>
      )}

      {/* User */}
      <div className={`flex items-center gap-3 border-t border-sidebar-line px-4 py-3 ${iconOnly ? 'flex-col' : ''}`}>
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-sidebar-2 text-sm font-black text-sidebar-fg ring-1 ring-sidebar-edge" aria-hidden="true">
          {(user?.name ?? '؟').trim().charAt(0)}
        </div>
        {!iconOnly && (
          <div className="min-w-0 flex-1 leading-tight">
            <div className="truncate text-sm font-semibold">{user?.name}</div>
            <div className="truncate text-xs text-sidebar-faint">{roleLabels[user?.user_type?.name] ?? user?.user_type?.name}</div>
          </div>
        )}
        <button type="button" onClick={logout} className="sb-icon-btn" title={iconOnly ? undefined : 'تسجيل الخروج'} aria-label="تسجيل الخروج" {...tipProps('تسجيل الخروج')}><LogOut className="h-5 w-5" /></button>
      </div>

      {iconOnly && tip && (
        <div className="sb-tip" role="tooltip" style={{ top: tip.top, left: tip.left - 8 }}>{tip.label}</div>
      )}
    </aside>
  )
}
