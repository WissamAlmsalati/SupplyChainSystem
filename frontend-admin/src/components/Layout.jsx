import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Nav from './Nav'

export default function Layout() {
  const { user, ready } = useAuth()

  if (!ready) return null
  if (!user) return <Navigate to="/login" replace />

  return (
    <div className="flex h-svh flex-col overflow-hidden bg-background lg:flex-row">
      <Nav />
      <main className="flex-1 space-y-4 overflow-auto bg-white px-4 pb-6 lg:px-6 lg:pb-8">
        <Outlet />
      </main>
    </div>
  )
}

