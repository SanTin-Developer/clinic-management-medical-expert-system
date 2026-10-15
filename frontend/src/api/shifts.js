import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const shiftApi = {
  list: (params) => api.get('/shifts', { params }),
  get: (id) => withData(api.get(`/shifts/${id}`)),
  create: (data) => withData(api.post('/shifts', data)),
  update: (id, data) => withData(api.put(`/shifts/${id}`, data)),
  remove: (id) => api.delete(`/shifts/${id}`),
  board: (params) => api.get('/shifts/board', { params }),
  uncovered: (params) => withData(api.get('/shifts/uncovered', { params })),
  publish: (id) => withData(api.post(`/shifts/${id}/publish`)),
  publishBulk: (data) => withData(api.post('/shifts/publish', data)),
}
