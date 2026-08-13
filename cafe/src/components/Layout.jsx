import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Nav from './Nav'

export default function Layout() {
  const { user, ready } = useAuth()

  if (!ready) return null
  if (!user) return <Navigate to="/login" replace />

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-background lg:flex-row">
      <Nav />
      <main className="flex-1 overflow-auto px-6 pb-6 lg:px-8 lg:pb-8">
        <Outlet />
      </main>
    </div>
  )
}
