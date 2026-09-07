import { useEffect, useState } from 'react'
import client from '../api/client'

const CACHE_KEY = 'premium-features-cache'

export function usePremiumFeatureActive(code) {
  const [features, setFeatures] = useState(() => {
    try {
      const cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null')
      return Array.isArray(cached) ? cached : null
    } catch {
      return null
    }
  })

  useEffect(() => {
    let active = true
    const apply = (items) => {
      try {
        localStorage.setItem(CACHE_KEY, JSON.stringify(items))
      } catch {}
      if (active) setFeatures(items)
    }
    const refresh = () => {
      client.get('/cafe/premium-features')
        .then((res) => {
          const payload = res.data
          apply(Array.isArray(payload) ? payload : payload?.data ?? [])
        })
        .catch(() => {})
    }
    // admin toggles write the shared cache in their tab — the storage event
    // syncs this tab instantly, no polling needed
    const onStorage = (e) => {
      if (e.key !== CACHE_KEY) return
      try {
        const items = JSON.parse(e.newValue || 'null')
        if (Array.isArray(items) && active) setFeatures(items)
      } catch {}
    }
    // ponytail: always revalidate on mount — a stale cache must not pin the UI
    // to an old state (iOS devices never see the admin tab's storage event).
    refresh()
    window.addEventListener('storage', onStorage)
    window.addEventListener('focus', refresh)
    return () => {
      active = false
      window.removeEventListener('storage', onStorage)
      window.removeEventListener('focus', refresh)
    }
  }, [])

  if (features === null) return false
  return features.some((f) => f.code === code && f.is_active)
}
