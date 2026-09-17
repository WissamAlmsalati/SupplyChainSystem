import client from '../api/client'

// Fetches a file through the authenticated API client (bearer token) and
// triggers a browser download. The server names the file in Content-Disposition.
export async function downloadFile(path, params = {}, fallbackName = 'report') {
  const res = await client.get(path, { params, responseType: 'blob' })
  const disposition = res.headers['content-disposition'] || ''
  const match = /filename="?([^";]+)"?/.exec(disposition)
  const name = match ? match[1] : fallbackName
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

// Turns an axios blob error back into the API's JSON message.
export async function downloadError(err, fallback = 'فشل تحميل الملف') {
  try {
    const text = await err.response?.data?.text?.()
    return text ? JSON.parse(text).message || fallback : fallback
  } catch {
    return fallback
  }
}
