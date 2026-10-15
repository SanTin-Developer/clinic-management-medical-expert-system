import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const queueApi = {
  list: (params) => api.get('/queue-entries', { params }),
  appointments: (params) => api.get('/queue-entries/appointments', { params }),
  get: (id) => withData(api.get(`/queue-entries/${id}`)),
  create: (data) => withData(api.post('/queue-entries', data)),
  update: (id, data) => withData(api.put(`/queue-entries/${id}`, data)),
  remove: (id) => api.delete(`/queue-entries/${id}`),
  call: (id) => withData(api.post(`/queue-entries/${id}/call`)),
  start: (id) => withData(api.post(`/queue-entries/${id}/start`)),
  complete: (id) => withData(api.post(`/queue-entries/${id}/complete`)),
  skip: (id) => withData(api.post(`/queue-entries/${id}/skip`)),
  cancel: (id) => withData(api.post(`/queue-entries/${id}/cancel`)),
}
