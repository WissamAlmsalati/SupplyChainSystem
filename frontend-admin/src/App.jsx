import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import RequirePermission from './components/RequirePermission'
import { useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Cafes from './pages/Cafes'
import CafeDetail from './pages/CafeDetail'
import CafeRegistrations from './pages/CafeRegistrations'
import CafeBranchDetail from './pages/CafeBranchDetail'
import Users from './pages/Users'
import UserDetail from './pages/UserDetail'
import Delegates from './pages/Delegates'
import DelegateDetail from './pages/DelegateDetail'
import Categories from './pages/Categories'
import Warehouses from './pages/Warehouses'
import WarehouseDetail from './pages/WarehouseDetail'
import CafeBranches from './pages/CafeBranches'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import VariantDetail from './pages/VariantDetail'
import Inventory from './pages/Inventory'
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
import NotFound from './pages/NotFound'
import { usePremiumFeatureActive } from './hooks/useApiResource'

function CafeRegistrationsRoute() {
  const hidden = usePremiumFeatureActive('cafe_auto_approve')
  if (hidden) return <Navigate to="/" replace />
  return (
    <RequirePermission permission="CAFES_VIEW">
      <CafeRegistrations />
    </RequirePermission>
  )
}

function HomeRedirect() {
  const { user } = useAuth()
  if (user?.user_type?.name === 'cafe') {
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
        <Route path="/cafes" element={<RequirePermission permission="CAFES_VIEW"><Cafes /></RequirePermission>} />
        <Route path="/cafes/:id" element={<RequirePermission permission="CAFES_VIEW"><CafeDetail /></RequirePermission>} />
        <Route path="/cafe-registrations" element={<CafeRegistrationsRoute />} />
        <Route path="/users" element={<RequirePermission permission="USERS_VIEW"><Users /></RequirePermission>} />
        <Route path="/users/:id" element={<RequirePermission permission="USERS_VIEW"><UserDetail /></RequirePermission>} />
        <Route path="/delegates" element={<RequirePermission permission="DELEGATES_VIEW"><Delegates /></RequirePermission>} />
        <Route path="/delegates/:id" element={<RequirePermission permission="DELEGATES_VIEW"><DelegateDetail /></RequirePermission>} />
        <Route path="/categories" element={<RequirePermission permission="CATEGORIES_VIEW"><Categories /></RequirePermission>} />
        <Route path="/warehouses" element={<RequirePermission permission="WAREHOUSES_VIEW"><Warehouses /></RequirePermission>} />
        <Route path="/warehouses/:id" element={<RequirePermission permission="WAREHOUSES_VIEW"><WarehouseDetail /></RequirePermission>} />
        <Route path="/cafe-branches" element={<RequirePermission permission="CAFE_BRANCHES_VIEW"><CafeBranches /></RequirePermission>} />
        <Route path="/cafe-branches/:id" element={<RequirePermission permission="CAFE_BRANCHES_VIEW"><CafeBranchDetail /></RequirePermission>} />
        <Route path="/products" element={<RequirePermission permission="PRODUCTS_VIEW"><Products /></RequirePermission>} />
        <Route path="/products/:id" element={<RequirePermission permission="PRODUCTS_VIEW"><ProductDetail /></RequirePermission>} />
        <Route path="/product-variants/:id" element={<RequirePermission permission="PRODUCTS_VIEW"><VariantDetail /></RequirePermission>} />
        <Route path="/inventory" element={<RequirePermission permission="INVENTORY_VIEW"><Inventory /></RequirePermission>} />
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
