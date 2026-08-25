import { useState } from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Nav from './Nav'
import Header from './Header'

export default function Layout() {
  const { user, ready } = useAuth()
  const [mobileNavOpen, setMobileNavOpen] = useState(false)

  if (!ready) return null
  if (!user) return <Navigate to="/login" replace />

  const isDelegate = user?.user_type?.name === 'delegate'

  return (
    <div className="flex h-screen flex-col overflow-hidden bg-background">
      {!isDelegate && <Header onMenuClick={() => setMobileNavOpen(true)} />}
      <div className="flex flex-1 overflow-hidden">
        <Nav
          mobileOpen={mobileNavOpen}
          onMobileClose={() => setMobileNavOpen(false)}
          showDesktop={isDelegate}
        />
        <main className="flex-1 overflow-auto px-4 pb-6 lg:px-8 lg:pb-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
