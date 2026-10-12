import { api } from './client.js'

export const appointmentApi = {
  list: (params) => api.get('/appointments', { params }),
  get: (id) => api.get(`/appointments/${id}`).then((r) => r.appointment),
  create: (data) => api.post('/appointments', data).then((r) => r.appointment),
  update: (id, data) => api.put(`/appointments/${id}`, data).then((r) => r.appointment),
  cancel: (id) => api.post(`/appointments/${id}/cancel`).then((r) => r.appointment),
  remove: (id) => api.delete(`/appointments/${id}`),
}