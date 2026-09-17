import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// ponytail: derive host/port/TLS from the page itself, so this works whether
// the app is served on a bare IP:port (no reverse proxy) or behind a domain
// with TLS on 443 — nginx proxies /app/local to Reverb either way.
const isSecure = window.location.protocol === 'https:'
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'local',
  wsHost: window.location.hostname,
  wsPort: window.location.port || (isSecure ? 443 : 80),
  wssPort: window.location.port || 443,
  forceTLS: isSecure,
  enabledTransports: [isSecure ? 'wss' : 'ws'],
})

export default echo
