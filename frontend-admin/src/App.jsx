import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import RequirePermission from './components/RequirePermission'
import { useAuth } from './context/AuthContext'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Cafes from './pages/Cafes'
import Users from './pages/Users'
import Delegates from './pages/Delegates'
import Categories from './pages/Categories'
import Suppliers from './pages/Suppliers'
import Warehouses from './pages/Warehouses'
import CafeBranches from './pages/CafeBranches'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import Inventory from './pages/Inventory'
import Orders from './pages/Orders'
import OrderDetail from './pages/OrderDetail'
import DeliveryZones from './pages/DeliveryZones'
import DeliveryZoneDetail from './pages/DeliveryZoneDetail'
import UserTypes from './pages/UserTypes'
import ActivityLogs from './pages/ActivityLogs'
import Map from './pages/Map'
import NotFound from './pages/NotFound'

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
        <Route path="/cafes" element={<RequirePermission permission="CAFES_VIEW"><Cafes /></RequirePermission>} />
        <Route path="/users" element={<RequirePermission permission="USERS_VIEW"><Users /></RequirePermission>} />
        <Route path="/delegates" element={<RequirePermission permission="DELEGATES_VIEW"><Delegates /></RequirePermission>} />
        <Route path="/categories" element={<RequirePermission permission="CATEGORIES_VIEW"><Categories /></RequirePermission>} />
        <Route path="/suppliers" element={<RequirePermission permission="SUPPLIERS_VIEW"><Suppliers /></RequirePermission>} />
        <Route path="/warehouses" element={<RequirePermission permission="WAREHOUSES_VIEW"><Warehouses /></RequirePermission>} />
        <Route path="/cafe-branches" element={<RequirePermission permission="CAFE_BRANCHES_VIEW"><CafeBranches /></RequirePermission>} />
        <Route path="/products" element={<RequirePermission permission="PRODUCTS_VIEW"><Products /></RequirePermission>} />
        <Route path="/products/:id" element={<RequirePermission permission="PRODUCTS_VIEW"><ProductDetail /></RequirePermission>} />
        <Route path="/inventory" element={<RequirePermission permission="INVENTORY_VIEW"><Inventory /></RequirePermission>} />
        <Route path="/orders" element={<RequirePermission permission="ORDERS_VIEW"><Orders /></RequirePermission>} />
        <Route path="/orders/:id" element={<RequirePermission permission="ORDERS_VIEW"><OrderDetail /></RequirePermission>} />
        <Route path="/delivery-zones" element={<RequirePermission permission="DELIVERY_ZONES_VIEW"><DeliveryZones /></RequirePermission>} />
        <Route path="/delivery-zones/:id" element={<RequirePermission permission="DELIVERY_ZONES_VIEW"><DeliveryZoneDetail /></RequirePermission>} />
        <Route path="/user-types" element={<RequirePermission permission="USER_TYPES_VIEW"><UserTypes /></RequirePermission>} />
        <Route path="/activity-logs" element={<RequirePermission permission="ACTIVITY_LOGS_VIEW"><ActivityLogs /></RequirePermission>} />
        <Route path="/map" element={<Map />} />
      </Route>
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
