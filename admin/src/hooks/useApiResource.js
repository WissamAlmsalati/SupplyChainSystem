import { useEffect, useState, useCallback } from 'react'
import client from '../api/client'

export function useApiResource(path) {
  const basePath = path.split('?')[0]
  const queryString = path.split('?')[1] || ''
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const buildPath = useCallback(() => {
    const params = new URLSearchParams(queryString)
    params.set('page', String(page))
    return `${basePath}?${params.toString()}`
  }, [queryString, page])

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
    setPage(1)
  }, [path])

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
