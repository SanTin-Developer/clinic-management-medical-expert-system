import axios from 'axios'
import { tokenStorage } from '../utils/storage.js'

export const API_BASE_URL =
  import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'

export const AUTH_EVENTS = {
  unauthorized: 'auth:unauthorized',
}

export class ApiError extends Error {
  constructor(message, status, payload) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.payload = payload
  }
}

const httpClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 60_000,
  headers: { Accept: 'application/json' },
})

httpClient.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

function extractMessage(error) {
  const status = error.response?.status
  const payload = error.response?.data

  if (typeof payload?.message === 'string' && payload.message.length) {
    return payload.message
  }
  if (typeof payload?.detail === 'string') {
    return payload.detail
  }
  if (payload?.errors) {
    const values = Object.values(payload.errors).filter(Array.isArray)
    if (values.length > 0 && values[0].length > 0) {
      return values[0][0]
    }
  }
  if (error.code === 'ECONNABORTED') {
    return 'The request timed out. Please try again.'
  }
  if (!status) {
    return 'Unable to reach the server. Check your connection and try again.'
  }
  return `Request failed (${status}).`
}

httpClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      tokenStorage.clear()
      window.dispatchEvent(new CustomEvent(AUTH_EVENTS.unauthorized))
    }
    if (!(error instanceof ApiError)) {
      const apiError = new ApiError(
        extractMessage(error),
        error.response?.status ?? null,
        error.response?.data ?? null,
      )
      // Preserve the original axios response for call sites that inspect
      // err.response (headers, blob bodies, etc.).
      apiError.response = error.response
      error = apiError
    }
    return Promise.reject(error)
  },
)

const unwrap = (promise) => promise.then((res) => res.data)

export const api = {
  get: (path, config) => unwrap(httpClient.get(path, config)),
  post: (path, data, config) => unwrap(httpClient.post(path, data, config)),
  put: (path, data, config) => unwrap(httpClient.put(path, data, config)),
  patch: (path, data, config) => unwrap(httpClient.patch(path, data, config)),
  delete: (path, config) => unwrap(httpClient.delete(path, config)),
}

export { httpClient }