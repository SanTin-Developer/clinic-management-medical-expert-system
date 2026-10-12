import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { API_BASE_URL } from '../api/client.js'
import { tokenStorage } from '../utils/storage.js'

const APP_KEY = import.meta.env.VITE_REVERB_APP_KEY
const AUTH_ORIGIN = API_BASE_URL.replace(/\/api\/v1\/?$/, '')

let instance = null

function buildEcho() {
  window.Pusher = Pusher

  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'
  const useTls = scheme === 'https'

  return new Echo({
    broadcaster: 'reverb',
    key: APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: useTls,
    enabledTransports: useTls ? ['wss'] : ['ws'],
    authorizer: (channel) => (socketId, callback) => {
      const token = tokenStorage.get()

      fetch(`${AUTH_ORIGIN}/broadcasting/auth`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
      })
        .then((response) => {
          if (!response.ok) throw new Error(`Broadcast auth failed (${response.status}).`)
          return response.json()
        })
        .then((data) => callback(null, data))
        .catch((error) => callback(error, null))
    },
  })
}

export function isRealtimeConfigured() {
  return Boolean(APP_KEY)
}

export function getEcho() {
  if (!isRealtimeConfigured()) return null
  if (!instance) instance = buildEcho()
  return instance
}

export function disconnectEcho() {
  if (instance) {
    instance.disconnect()
    instance = null
  }
}
