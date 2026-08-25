import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

// ponytail: backend serializes the relation as userType (camelCase), but the
// admin UI was built against user_type. Normalize once on load instead of
// spreading the mismatch across every consumer.
function normalizeUser(raw) {
  if (!raw) return raw
  return {
    ...raw,
    user_type: raw.user_type ?? raw.userType,
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      client.get('/me')
        .then((res) => setUser(normalizeUser(res.data)))
        .catch(() => localStorage.removeItem('token'))
        .finally(() => setReady(true))
    } else {
      setReady(true)
    }
  }, [])

  const login = async (email, password) => {
    const { data } = await client.post('/login', { email, password })
    localStorage.setItem('token', data.token)
    const res = await client.get('/me')
    const normalized = normalizeUser(res.data)
    localStorage.setItem('user', JSON.stringify(normalized))
    setUser(normalized)
    return normalized
  }

  const logout = async () => {
    try {
      await client.post('/logout')
    } catch {
      // ignore
    }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }

  const hasPermission = useCallback((code) => {
    if (!user) return false
    if (user.user_type?.name === 'super_admin') return true
    const codes = user.user_type?.permissions?.map((p) => p.code) ?? []
    return codes.includes(code)
  }, [user])

  const hasAnyPermission = useCallback((codes) => {
    return codes.some((code) => hasPermission(code))
  }, [hasPermission])

  return (
    <AuthContext.Provider value={{ user, ready, login, logout, hasPermission, hasAnyPermission }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
