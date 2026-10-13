import { api } from './client.js'

export const searchApi = {
  global: (q) => api.get('/search', { params: { q } }),
}
