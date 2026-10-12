import { api } from './client.js'

export const userApi = {
  list: (params) => api.get('/users', { params }),
  get: (id) => api.get(`/users/${id}`).then((r) => r.user),
  create: (data) => api.post('/users', data).then((r) => r.user),
  update: (id, data) => api.put(`/users/${id}`, data).then((r) => r.user),
  remove: (id) => api.delete(`/users/${id}`),
}