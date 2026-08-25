import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import DelegateDashboard from './pages/DelegateDashboard'
import Orders from './pages/Orders'
import DelegateOrders from './pages/DelegateOrders'
import DelegateOrderDetail from './pages/DelegateOrderDetail'
import CafeOrderDetail from './pages/CafeOrderDetail'
import Branches from './pages/Branches'
import Profile from './pages/Profile'
import Products from './pages/Products'
import ProductDetail from './pages/ProductDetail'
import Cart from './pages/Cart'
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
      <Route element={<Layout />}>
        <Route path="/" element={<HomeRedirect />} />
        <Route path="/delegate" element={isDelegate ? <DelegateDashboard /> : <Navigate to="/" replace />} />
        <Route path="/orders" element={isDelegate ? <DelegateOrders /> : <Orders />} />
        <Route path="/orders/:id" element={isDelegate ? <DelegateOrderDetail /> : <CafeOrderDetail />} />
        <Route path="/branches" element={isDelegate ? <Navigate to="/" replace /> : <Branches />} />
        <Route path="/products" element={isDelegate ? <Navigate to="/" replace /> : <Products />} />
        <Route path="/products/:id" element={isDelegate ? <Navigate to="/" replace /> : <ProductDetail />} />
        <Route path="/cart" element={<Cart />} />
        <Route path="/profile" element={<Profile />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
