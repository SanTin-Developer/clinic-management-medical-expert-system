import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const leaveApi = {
  list: (params) => api.get('/leave-requests', { params }),
  get: (id) => withData(api.get(`/leave-requests/${id}`)),
  create: (data) => withData(api.post('/leave-requests', data)),
  update: (id, data) => withData(api.put(`/leave-requests/${id}`, data)),
  approve: (id, data) => withData(api.post(`/leave-requests/${id}/approve`, data ?? {})),
  reject: (id, data) => withData(api.post(`/leave-requests/${id}/reject`, data ?? {})),
  cancel: (id) => withData(api.post(`/leave-requests/${id}/cancel`)),
  remove: (id) => api.delete(`/leave-requests/${id}`),
}
