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
