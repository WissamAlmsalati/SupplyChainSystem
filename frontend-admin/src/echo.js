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
  // Driver positions are on a private channel. The handshake is authorised
  // with the bearer token, read at the moment of subscribing so a fresh
  // sign-in is used rather than whatever was stored when the page loaded.
  authorizer: (channel) => ({
    authorize: (socketId, callback) => {
      fetch('/api/v1/broadcasting/auth', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('token') ?? ''}`,
        },
        body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
      })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`broadcast auth ${res.status}`))))
        .then((data) => callback(null, data))
        .catch((error) => callback(error))
    },
  }),
})

export default echo
