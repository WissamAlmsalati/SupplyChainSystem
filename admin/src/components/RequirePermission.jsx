import { Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function RequirePermission({ permission, superAdmin, children }) {
  const { user, ready, hasPermission, hasAnyPermission } = useAuth()

  if (!ready) return null

  if (!user) return <Navigate to="/login" replace />

  if (superAdmin) {
    return user.user_type?.name === 'super_admin' ? children : <Navigate to="/" replace />
  }

  if (permission) {
    const codes = Array.isArray(permission) ? permission : [permission]
    return hasAnyPermission(codes) ? children : <Navigate to="/" replace />
  }

  return children
}
