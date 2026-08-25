import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'local',
  wsHost: window.location.hostname,
  wsPort: 80,
  wssPort: 443,
  forceTLS: false,
  enabledTransports: ['ws'],
})

export default echo
