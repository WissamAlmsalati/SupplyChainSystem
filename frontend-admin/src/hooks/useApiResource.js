import { useEffect, useState, useCallback, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import client from '../api/client'

export function useApiResource(path, extraParams = {}, options = {}) {
  const { persistPage = true } = options
  const basePath = path.split('?')[0]
  const queryString = path.split('?')[1] || ''
  const [searchParams, setSearchParams] = useSearchParams()
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })
  const [internalPage, setInternalPage] = useState(1)
  const extraParamsKey = JSON.stringify(extraParams)
  const previousParamsKey = useRef(extraParamsKey)
  const previousPath = useRef(basePath)

  const urlPage = Number(searchParams.get('page')) > 0 ? Number(searchParams.get('page')) : 1
  const page = persistPage ? urlPage : internalPage

  const setPage = useCallback((newPage) => {
    if (persistPage) {
      setSearchParams((prev) => {
        prev.set('page', String(newPage))
        return prev
      }, { replace: true })
    } else {
      setInternalPage(newPage)
    }
  }, [persistPage, setSearchParams])

  const buildPath = useCallback(() => {
    const params = new URLSearchParams(queryString)
    Object.entries(extraParams).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.set(key, String(value))
      }
    })
    params.set('page', String(page))
    return `${basePath}?${params.toString()}`
  }, [queryString, page, extraParamsKey])

  const fetch = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const res = await client.get(buildPath())
      const payload = res.data
      if (Array.isArray(payload)) {
        setItems(payload)
        setPagination({ current_page: 1, last_page: 1, per_page: payload.length, total: payload.length })
      } else if (payload?.data) {
        setItems(payload.data)
        setPagination({
          current_page: payload.current_page ?? 1,
          last_page: payload.last_page ?? 1,
          per_page: payload.per_page ?? 15,
          total: payload.total ?? 0,
        })
      } else {
        setItems([])
        setPagination({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
      }
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'فشل التحميل')
      setItems([])
    } finally {
      setLoading(false)
    }
  }, [buildPath])

  useEffect(() => {
    const pathChanged = previousPath.current !== basePath
    const paramsChanged = previousParamsKey.current !== extraParamsKey

    if (!pathChanged && !paramsChanged) {
      return
    }

    previousPath.current = basePath
    previousParamsKey.current = extraParamsKey

    if (persistPage) {
      setSearchParams((prev) => {
        prev.delete('page')
        return prev
      }, { replace: true })
    } else {
      setInternalPage(1)
    }
  }, [basePath, extraParamsKey, persistPage, setSearchParams])

  useEffect(() => {
    fetch()
  }, [fetch])

  const create = async (data) => {
    const res = await (data instanceof FormData
      ? client.postForm(basePath, data)
      : client.post(basePath, data))
    await fetch()
    return res.data
  }

  const update = async (id, data) => {
    const res = await (data instanceof FormData
      ? client.putForm(`${basePath}/${id}`, data)
      : client.put(`${basePath}/${id}`, data))
    await fetch()
    return res.data
  }

  const remove = async (id) => {
    await client.delete(`${basePath}/${id}`)
    await fetch()
  }

  return { items, loading, error, pagination, setPage, fetch, create, update, remove }
}

export function useApiList(path) {
  const [items, setItems] = useState([])

  useEffect(() => {
    let active = true
    client.get(path).then((res) => {
      const payload = res.data
      if (active) setItems(payload?.data ?? payload ?? [])
    })
    return () => { active = false }
  }, [path])

  return items
}

const PREMIUM_FEATURES_CACHE_KEY = 'premium-features-cache'

export function usePremiumFeatureActive(code) {
  // ponytail: localStorage cache kills the first-paint flicker — we render the
  // last-known state instantly, then revalidate in the background and update.
  const [features, setFeatures] = useState(() => {
    try {
      const cached = JSON.parse(localStorage.getItem(PREMIUM_FEATURES_CACHE_KEY) || 'null')
      return Array.isArray(cached) ? cached : null
    } catch {
      return null
    }
  })

  useEffect(() => {
    let active = true
    const apply = (items) => {
      try {
        localStorage.setItem(PREMIUM_FEATURES_CACHE_KEY, JSON.stringify(items))
      } catch {}
      if (active) setFeatures(items)
    }
    const refresh = () => {
      client.get('/premium-features')
        .then((res) => {
          const payload = res.data
          apply(Array.isArray(payload) ? payload : payload?.data ?? [])
        })
        .catch(() => {})
    }
    // another tab (e.g. admin toggling a feature) updates the shared cache —
    // the storage event syncs this tab instantly, no polling needed
    const onStorage = (e) => {
      if (e.key !== PREMIUM_FEATURES_CACHE_KEY) return
      try {
        const items = JSON.parse(e.newValue || 'null')
        if (Array.isArray(items) && active) setFeatures(items)
      } catch {}
    }
    refresh()
    window.addEventListener('storage', onStorage)
    window.addEventListener('focus', refresh)
    return () => {
      active = false
      window.removeEventListener('storage', onStorage)
      window.removeEventListener('focus', refresh)
    }
  }, [])

  if (features === null) return null
  return features.some((feature) => feature.code === code && feature.is_active)
}
