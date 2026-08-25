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

client.putForm = (path, formData) =>
  client.put(path, formData, { headers: { 'Content-Type': 'multipart/form-data' } })

export default client
