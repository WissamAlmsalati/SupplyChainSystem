import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import App from './App.jsx'
import './index.css'

// Zoom is an accessibility tool in a browser tab, so it stays there — but in an
// installed window a pinch or a double tap reads as a glitch, not a gesture.
const installed = window.matchMedia('(display-mode: standalone)').matches
  || window.matchMedia('(display-mode: fullscreen)').matches
  || window.navigator.standalone === true

if (installed) {
  document.documentElement.classList.add('installed')
  document
    .querySelector('meta[name="viewport"]')
    ?.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover')
}

// Installable, and openable with no network. Dev is left alone so a stale
// worker never serves yesterday's bundle over the Vite server.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
  })
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
