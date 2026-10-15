import { api } from './client.js'

function stepUpHeaders(token) {
  return token ? { headers: { 'X-Step-Up-Token': token } } : {}
}

export const userApi = {
  list: (params) => api.get('/users', { params }),
  get: (id) => api.get(`/users/${id}`).then((r) => r.user),
  update: (id, data) => api.put(`/users/${id}`, data).then((r) => r.user),
  remove: (id) => api.delete(`/users/${id}`),
  suspend: (id, { reason, stepUpToken } = {}) =>
    api.post(`/users/${id}/suspend`, reason ? { reason } : {}, stepUpHeaders(stepUpToken)),
  activate: (id) => api.post(`/users/${id}/activate`),
  disable: (id, { stepUpToken } = {}) =>
    api.post(`/users/${id}/disable`, {}, stepUpHeaders(stepUpToken)),
}
