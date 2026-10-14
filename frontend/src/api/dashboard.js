import { api } from './client.js'

export const dashboardApi = {
  analytics: () => api.get('/dashboard/analytics').then((r) => r.data),
}
