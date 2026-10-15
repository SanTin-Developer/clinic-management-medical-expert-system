import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const shiftApi = {
  list: (params) => api.get('/shifts', { params }),
  get: (id) => withData(api.get(`/shifts/${id}`)),
  create: (data) => withData(api.post('/shifts', data)),
  update: (id, data) => withData(api.put(`/shifts/${id}`, data)),
  remove: (id) => api.delete(`/shifts/${id}`),
}
