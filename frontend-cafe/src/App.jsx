import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import DelegateDashboard from './pages/DelegateDashboard'
import Orders from './pages/Orders'
import DelegateOrders from './pages/DelegateOrders'
import DelegateOrderDetail from './pages/DelegateOrderDetail'
import Branches from './pages/Branches'
import Profile from './pages/Profile'
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
      <Route element={<Layout />}>
        <Route path="/" element={<HomeRedirect />} />
        <Route path="/delegate" element={isDelegate ? <DelegateDashboard /> : <Navigate to="/" replace />} />
        <Route path="/orders" element={isDelegate ? <DelegateOrders /> : <Orders />} />
        <Route path="/orders/:id" element={isDelegate ? <DelegateOrderDetail /> : <Navigate to="/orders" replace />} />
        <Route path="/branches" element={isDelegate ? <Navigate to="/" replace /> : <Branches />} />
        <Route path="/profile" element={<Profile />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
