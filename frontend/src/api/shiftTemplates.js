import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const shiftTemplateApi = {
  list: () => api.get('/shift-templates').then((r) => r.data),
  create: (data) => withData(api.post('/shift-templates', data)),
  update: (id, data) => withData(api.put(`/shift-templates/${id}`, data)),
  remove: (id) => api.delete(`/shift-templates/${id}`),
}
