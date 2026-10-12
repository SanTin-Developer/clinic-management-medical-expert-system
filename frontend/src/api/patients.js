import { api } from './client.js'

export const patientApi = {
  list: (params) => api.get('/patients', { params }),
  get: (id) => api.get(`/patients/${id}`).then((r) => r.patient),
  create: (data) => api.post('/patients', data).then((r) => r.data),
  update: (id, data) => api.put(`/patients/${id}`, data).then((r) => r.data),
  remove: (id) => api.delete(`/patients/${id}`),
}