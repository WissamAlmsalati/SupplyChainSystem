import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import RequirePermission from './components/RequirePermission'
import { useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Addresses from './pages/Addresses'
import AddressDetail from './pages/AddressDetail'
import Users from './pages/Users'
import UserDetail from './pages/UserDetail'
import Customers from './pages/Customers'
import Delegates from './pages/Delegates'
import DelegateDetail from './pages/DelegateDetail'
import DelegatePerformance from './pages/DelegatePerformance'
import Returns from './pages/Returns'
import Categories from './pages/Categories'
import Warehouses from './pages/Warehouses'
import WarehouseDetail from './pages/WarehouseDetail'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import VariantDetail from './pages/VariantDetail'
import Inventory from './pages/Inventory'
import Wallets from './pages/Wallets'
import WalletDetail from './pages/WalletDetail'
import WalletTopups from './pages/WalletTopups'
import Custody from './pages/Custody'
import FeaturedSections from './pages/FeaturedSections'
import CustodyDetail from './pages/CustodyDetail'
import Orders from './pages/Orders'
import OrderDetail from './pages/OrderDetail'
import DeliveryZones from './pages/DeliveryZones'
import DeliveryZoneDetail from './pages/DeliveryZoneDetail'
import UserTypes from './pages/UserTypes'
import UserTypeDetail from './pages/UserTypeDetail'
import ActivityLogs from './pages/ActivityLogs'
import Notifications from './pages/Notifications'
import NotificationDetail from './pages/NotificationDetail'
import Promos from './pages/Promos'
import PremiumFeatures from './pages/PremiumFeatures'
import Map from './pages/Map'
import MonthlyStats from './pages/MonthlyStats'
import Reports from './pages/Reports'
import NotFound from './pages/NotFound'

function HomeRedirect() {
  const { user } = useAuth()
  if (user?.user_type?.name === 'customer') {
    return <Navigate to="/orders" replace />
  }
  return <Dashboard />
}

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Layout />}>
        <Route path="/" element={<HomeRedirect />} />
        <Route path="/statistics/:yearMonth" element={<RequirePermission permission="DASHBOARD_VIEW"><MonthlyStats /></RequirePermission>} />
        <Route path="/users" element={<RequirePermission permission="USERS_VIEW"><Users /></RequirePermission>} />
        <Route path="/users/:id" element={<RequirePermission permission="USERS_VIEW"><UserDetail /></RequirePermission>} />
        <Route path="/customers" element={<RequirePermission permission="USERS_VIEW"><Customers /></RequirePermission>} />
        <Route path="/delegates" element={<RequirePermission permission="DELEGATES_VIEW"><Delegates /></RequirePermission>} />
        <Route path="/delegate-performance" element={<RequirePermission permission="REPORTS_VIEW"><DelegatePerformance /></RequirePermission>} />
        <Route path="/returns" element={<RequirePermission permission="RETURNS_VIEW"><Returns /></RequirePermission>} />
        <Route path="/delegates/:id" element={<RequirePermission permission="DELEGATES_VIEW"><DelegateDetail /></RequirePermission>} />
        <Route path="/categories" element={<RequirePermission permission="CATEGORIES_VIEW"><Categories /></RequirePermission>} />
        <Route path="/warehouses" element={<RequirePermission permission="WAREHOUSES_VIEW"><Warehouses /></RequirePermission>} />
        <Route path="/warehouses/:id" element={<RequirePermission permission="WAREHOUSES_VIEW"><WarehouseDetail /></RequirePermission>} />
        <Route path="/addresses" element={<RequirePermission permission="CUSTOMER_BRANCHES_VIEW"><Addresses /></RequirePermission>} />
        <Route path="/addresses/:id" element={<RequirePermission permission="CUSTOMER_BRANCHES_VIEW"><AddressDetail /></RequirePermission>} />
        <Route path="/products" element={<RequirePermission permission="PRODUCTS_VIEW"><Products /></RequirePermission>} />
        <Route path="/products/:id" element={<RequirePermission permission="PRODUCTS_VIEW"><ProductDetail /></RequirePermission>} />
        <Route path="/product-variants/:id" element={<RequirePermission permission="PRODUCTS_VIEW"><VariantDetail /></RequirePermission>} />
        <Route path="/inventory" element={<RequirePermission permission="INVENTORY_VIEW"><Inventory /></RequirePermission>} />
        <Route path="/wallets" element={<RequirePermission permission="WALLETS_VIEW"><Wallets /></RequirePermission>} />
        <Route path="/wallets/:id" element={<RequirePermission permission="WALLETS_VIEW"><WalletDetail /></RequirePermission>} />
        <Route path="/wallet-topups" element={<RequirePermission permission="WALLET_TOPUPS_VIEW"><WalletTopups /></RequirePermission>} />
        <Route path="/wallet-topups/:id" element={<RequirePermission permission="WALLET_TOPUPS_VIEW"><WalletTopups /></RequirePermission>} />
        <Route path="/featured-sections" element={<RequirePermission permission="FEATURED_SECTIONS_VIEW"><FeaturedSections /></RequirePermission>} />
        <Route path="/custody" element={<RequirePermission permission="CUSTODY_VIEW"><Custody /></RequirePermission>} />
        <Route path="/custody/:id" element={<RequirePermission permission="CUSTODY_VIEW"><CustodyDetail /></RequirePermission>} />
        <Route path="/reports" element={<RequirePermission permission="REPORTS_VIEW"><Reports /></RequirePermission>} />
        <Route path="/orders" element={<RequirePermission permission="ORDERS_VIEW"><Orders /></RequirePermission>} />
        <Route path="/orders/:id" element={<RequirePermission permission="ORDERS_VIEW"><OrderDetail /></RequirePermission>} />
        <Route path="/delivery-zones" element={<RequirePermission permission="DELIVERY_ZONES_VIEW"><DeliveryZones /></RequirePermission>} />
        <Route path="/delivery-zones/:id" element={<RequirePermission permission="DELIVERY_ZONES_VIEW"><DeliveryZoneDetail /></RequirePermission>} />
        <Route path="/user-types" element={<RequirePermission permission="USER_TYPES_VIEW"><UserTypes /></RequirePermission>} />
        <Route path="/user-types/:id" element={<RequirePermission permission="USER_TYPES_VIEW"><UserTypeDetail /></RequirePermission>} />
        <Route path="/activity-logs" element={<RequirePermission permission="ACTIVITY_LOGS_VIEW"><ActivityLogs /></RequirePermission>} />
        <Route path="/notifications" element={<Notifications />} />
        <Route path="/notifications/:id" element={<NotificationDetail />} />
        <Route path="/promos" element={<Promos />} />
        <Route path="/premium-features" element={<PremiumFeatures />} />
        <Route path="/map" element={<Map />} />
      </Route>
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
