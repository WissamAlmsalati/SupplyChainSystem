import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      client.get('/me')
        .then((res) => setUser(res.data))
        .catch(() => localStorage.removeItem('token'))
        .finally(() => setReady(true))
    } else {
      setReady(true)
    }
  }, [])

  // A phone number is only unique within a user type, so the app has to say
  // which one it is signing in as — there is a door per app.
  const login = async (phoneNumber, password, role = 'customer') => {
    const { data } = await client.post(`/${role}/login`, { phone_number: phoneNumber, password })
    localStorage.setItem('token', data.token)
    const res = await client.get('/me')
    localStorage.setItem('user', JSON.stringify(res.data))
    setUser(res.data)
    return res.data
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

  return (
    <AuthContext.Provider value={{ user, ready, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
