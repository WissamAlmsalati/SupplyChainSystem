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

// Asks who is signed in, and only gives the token up when the server says it
// is no good (401/403). A rate limit, a restart or a dropped connection is not
// a reason to sign somebody out: it is tried again, a little later each time.
const whoAmI = async (path, attempt = 0) => {
  try {
    return await client.get(path)
  } catch (err) {
    const status = err.response?.status
    if (status === 401 || status === 403 || attempt >= 4) throw err
    const wait = Number(err.response?.headers?.['retry-after']) * 1000 || 1500 * (attempt + 1)
    await new Promise((resolve) => setTimeout(resolve, Math.min(wait, 15000)))
    return whoAmI(path, attempt + 1)
  }
}
const tokenIsDead = (err) => [401, 403].includes(err.response?.status)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [premiumFeatures, setPremiumFeatures] = useState([])
  const [ready, setReady] = useState(false)

  const fetchFeatures = useCallback(async () => {
    try {
      const { data } = await client.get('/premium-features')
      setPremiumFeatures(data ?? [])
    } catch {
      setPremiumFeatures([])
    }
  }, [])

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      whoAmI('/me')
        .then((res) => {
          setUser(normalizeUser(res.data))
          fetchFeatures()
        })
        .catch((err) => { if (tokenIsDead(err)) localStorage.removeItem('token') })
        .finally(() => setReady(true))
    } else {
      setReady(true)
    }
  }, [fetchFeatures])

  const login = async (email, password) => {
    // Each app signs in at its own door; this one looks the account up among
    // admins and super admins.
    const { data } = await client.post('/admin/login', { email, password })
    localStorage.setItem('token', data.token)
    const res = await client.get('/me')
    const normalized = normalizeUser(res.data)
    localStorage.setItem('user', JSON.stringify(normalized))
    setUser(normalized)
    await fetchFeatures()
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

  const hasFeature = useCallback((code) => {
    return premiumFeatures.includes(code)
  }, [premiumFeatures])

  return (
    <AuthContext.Provider value={{ user, ready, login, logout, hasPermission, hasAnyPermission, hasFeature }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
