import client from '../api/client'

// Downloads a file through the authenticated API client (bearer token).
export async function downloadFile(path, params = {}, fallbackName = 'file.pdf') {
  const res = await client.get(path, { params, responseType: 'blob' })
  const match = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '')
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = match ? match[1] : fallbackName
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
