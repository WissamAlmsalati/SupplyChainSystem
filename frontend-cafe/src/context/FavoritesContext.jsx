import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from './AuthContext'

const FavoritesContext = createContext(null)

// Keeps the ids of the customer's favorite products so every card shows the right heart.
export function FavoritesProvider({ children }) {
  const { user } = useAuth()
  const [ids, setIds] = useState(() => new Set())

  const refresh = useCallback(async () => {
    try {
      const res = await client.get('/cafe/favorites/ids')
      setIds(new Set(res.data?.data ?? []))
    } catch {
      setIds(new Set())
    }
  }, [])

  // Login and logout happen without a page reload, so follow the signed-in user.
  useEffect(() => {
    if (user && user.user_type?.name !== 'delegate') refresh()
    else setIds(new Set())
  }, [user?.id, refresh])

  const isFavorite = useCallback((productId) => ids.has(Number(productId)), [ids])

  // Optimistic toggle; reverts if the request fails.
  const toggle = useCallback(async (productId) => {
    const id = Number(productId)
    const wasFavorite = ids.has(id)
    setIds((prev) => {
      const next = new Set(prev)
      wasFavorite ? next.delete(id) : next.add(id)
      return next
    })
    try {
      if (wasFavorite) await client.delete(`/cafe/favorites/${id}`)
      else await client.post('/cafe/favorites', { product_id: id })
    } catch (err) {
      setIds((prev) => {
        const next = new Set(prev)
        wasFavorite ? next.add(id) : next.delete(id)
        return next
      })
      throw err
    }
    return !wasFavorite
  }, [ids])

  return (
    <FavoritesContext.Provider value={{ isFavorite, toggle, refresh, count: ids.size }}>
      {children}
    </FavoritesContext.Provider>
  )
}

export function useFavorites() {
  return useContext(FavoritesContext)
}
