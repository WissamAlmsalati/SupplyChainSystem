import { useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'

// Small live numbers for the sidebar: what needs a human right now.
// Each one is a 1-row list call whose meta.total is the count.
const SOURCES = {
  pendingOrders: { path: '/orders', params: { status: 'pending' }, permission: 'ORDERS_VIEW' },
  cancellationRequests: { path: '/orders', params: { status: 'cancellation_requested' }, permission: 'ORDERS_VIEW' },
  pendingTopups: { path: '/wallet-topups', params: { status: 'pending' }, permission: 'WALLET_TOPUPS_VIEW' },
  unreadNotifications: { path: '/notifications/unread-count', params: {}, pick: (d) => d?.count ?? 0 },
}

export function useLiveCounts(intervalMs = 60000) {
  const { hasPermission } = useAuth()
  const [counts, setCounts] = useState({})

  useEffect(() => {
    let cancelled = false
    const load = async () => {
      const entries = await Promise.all(
        Object.entries(SOURCES)
          .filter(([, s]) => !s.permission || hasPermission(s.permission))
          .map(async ([key, s]) => {
            try {
              const res = await client.get(s.path, { params: { ...s.params, per_page: 1 } })
              return [key, s.pick ? s.pick(res.data) : res.data?.meta?.total ?? 0]
            } catch {
              return [key, null]
            }
          }),
      )
      if (!cancelled) setCounts(Object.fromEntries(entries))
    }
    load()
    const id = setInterval(load, intervalMs)
    const onFocus = () => load()
    window.addEventListener('focus', onFocus)
    return () => {
      cancelled = true
      clearInterval(id)
      window.removeEventListener('focus', onFocus)
    }
  }, [hasPermission, intervalMs])

  return counts
}
