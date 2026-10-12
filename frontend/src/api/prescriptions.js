import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const prescriptionApi = {
  list: (params) => api.get('/prescriptions', { params }),
  get: (id) => withData(api.get(`/prescriptions/${id}`)),
  create: (data) => withData(api.post('/prescriptions', data)),
  update: (id, data) => withData(api.put(`/prescriptions/${id}`, data)),
  cancel: (id) => withData(api.post(`/prescriptions/${id}/cancel`)),
  dispense: (id) => withData(api.post(`/prescriptions/${id}/dispense`)),
  remove: (id) => api.delete(`/prescriptions/${id}`),
}