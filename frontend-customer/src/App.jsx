import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import Login from './pages/Login'
import Register from './pages/Register'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import Dashboard from './pages/Dashboard'
import DelegateDashboard from './pages/DelegateDashboard'
import Orders from './pages/Orders'
import DelegateOrders from './pages/DelegateOrders'
import DelegateOrderDetail from './pages/DelegateOrderDetail'
import CustomerOrderDetail from './pages/CustomerOrderDetail'
import Addresses from './pages/Addresses'
import Profile from './pages/Profile'
import Notifications from './pages/Notifications'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import Cart from './pages/Cart'
import RecurringCarts from './pages/RecurringCarts'
import Wallet from './pages/Wallet'
import Favorites from './pages/Favorites'
import FeaturedSectionProducts from './pages/FeaturedSectionProducts'
import { useAuth } from './context/AuthContext'

function HomeRedirect() {
  const { user } = useAuth()
  if (user?.user_type?.name === 'delegate') {
    return <Navigate to="/delegate" replace />
  }
  return <Dashboard />
}

function App() {
  const { ready, user } = useAuth()

  if (!ready) return null

  const isDelegate = user?.user_type?.name === 'delegate'

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" replace /> : <Login />} />
      <Route path="/register" element={user ? <Navigate to="/" replace /> : <Register />} />
      <Route path="/forgot-password" element={user ? <Navigate to="/" replace /> : <ForgotPassword />} />
      <Route path="/reset-password" element={user ? <Navigate to="/" replace /> : <ResetPassword />} />
      <Route element={<Layout />}>
        <Route path="/" element={<HomeRedirect />} />
        <Route path="/delegate" element={isDelegate ? <DelegateDashboard /> : <Navigate to="/" replace />} />
        <Route path="/orders" element={isDelegate ? <DelegateOrders /> : <Orders />} />
        <Route path="/orders/:id" element={isDelegate ? <DelegateOrderDetail /> : <CustomerOrderDetail />} />
        <Route path="/addresses" element={isDelegate ? <Navigate to="/" replace /> : <Addresses />} />
        <Route path="/products" element={isDelegate ? <Navigate to="/" replace /> : <Products />} />
        <Route path="/products/:id" element={isDelegate ? <Navigate to="/" replace /> : <ProductDetail />} />
        <Route path="/cart" element={<Cart />} />
        <Route path="/recurring-carts" element={isDelegate ? <Navigate to="/" replace /> : <RecurringCarts />} />
        <Route path="/wallet" element={isDelegate ? <Navigate to="/" replace /> : <Wallet />} />
        <Route path="/favorites" element={isDelegate ? <Navigate to="/" replace /> : <Favorites />} />
        <Route path="/sections/:id" element={isDelegate ? <Navigate to="/" replace /> : <FeaturedSectionProducts />} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/notifications" element={<Notifications />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
