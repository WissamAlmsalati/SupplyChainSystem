import axios from 'axios'

const client = axios.create({
  baseURL: '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

client.postForm = (path, formData) =>
  client.post(path, formData, { headers: { 'Content-Type': 'multipart/form-data' } })

// ponytail: PHP only parses multipart bodies on POST — real PUT leaves $_FILES
// empty, so spoof the method (Laravel honors _method) to keep file uploads working.
client.putForm = (path, formData) => {
  formData.append('_method', 'PUT')
  return client.post(path, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}

// ponytail: a write that must happen once. The server remembers the answer to
// an Idempotency-Key for a day and replays it instead of doing the work again,
// so a double tap or a retry on a weak network cannot create two orders or two
// payments. The key is tied to "this path with this body" and lives until the
// server has answered: a repeat of the same action reuses it, and the next
// genuine action (even an identical order tomorrow) gets a fresh one.
// crypto.randomUUID only exists on https, and the app also runs on a bare IP.
const pendingKeys = new Map()
const newKey = () =>
  (globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`)
const fingerprint = (path, data) => {
  if (data instanceof FormData) {
    const parts = []
    data.forEach((v, k) => parts.push(`${k}=${v instanceof File ? `${v.name}:${v.size}` : v}`))
    return `${path}|${parts.join('&')}`
  }
  return `${path}|${JSON.stringify(data ?? null)}`
}

client.postOnce = async (path, data, config = {}) => {
  const print = fingerprint(path, data)
  if (!pendingKeys.has(print)) pendingKeys.set(print, newKey())
  const headers = { ...(config.headers || {}), 'Idempotency-Key': pendingKeys.get(print) }
  if (data instanceof FormData) headers['Content-Type'] = 'multipart/form-data'
  try {
    const res = await client.post(path, data, { ...config, headers })
    pendingKeys.delete(print)
    return res
  } catch (err) {
    // The server answered, so nothing is in doubt. Only a lost connection
    // leaves the outcome unknown, and that is when the key must survive.
    if (err.response) pendingKeys.delete(print)
    throw err
  }
}

export default client
