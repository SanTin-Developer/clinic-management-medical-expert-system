import { api } from './client.js'

export const auditApi = {
  list: (params) => api.get('/audit-logs', { params }),
  get: (id) => api.get(`/audit-logs/${id}`).then((r) => r.data),
}
