import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

// Everything this app calls lives under its own prefix, /customer/ or
// /delegate/, so the door it signed in through is remembered to reach
// /{door}/me and /{door}/logout on the next start.
const door = () => (localStorage.getItem('door') === 'delegate' ? 'delegate' : 'customer')

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      client.get(`/${door()}/me`)
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
    localStorage.setItem('door', role)
    const res = await client.get(`/${role}/me`)
    localStorage.setItem('user', JSON.stringify(res.data))
    setUser(res.data)
    return res.data
  }

  const logout = async () => {
    try {
      await client.post(`/${door()}/logout`)
    } catch {
      // ignore
    }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('door')
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
