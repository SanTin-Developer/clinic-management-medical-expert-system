const TOKEN_KEY = 'clinic.auth.token'

export const tokenStorage = {
  get() {
    try {
      return window.localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token) {
    try {
      window.localStorage.setItem(TOKEN_KEY, token)
    } catch {
      // Storage unavailable (e.g. private mode); session stays in memory only.
    }
  },
  clear() {
    try {
      window.localStorage.removeItem(TOKEN_KEY)
    } catch {
      // Ignore.
    }
  },
}

// Remove every trace of the session: the stored bearer token, any session
// storage entries, and all readable cookies for the current host.
export function clearStoredAuth() {
  tokenStorage.clear()

  try {
    window.sessionStorage.clear()
  } catch {
    // Ignore.
  }

  try {
    const hostname = window.location.hostname
    const cookies = document.cookie ? document.cookie.split(';') : []
    for (const cookie of cookies) {
      const name = cookie.split('=')[0]?.trim()
      if (!name) continue
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${hostname}`
    }
  } catch {
    // Ignore.
  }
}

