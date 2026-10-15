import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const announcementApi = {
  list: (params) => api.get('/announcements', { params }),
  get: (id) => withData(api.get(`/announcements/${id}`)),
  create: (data) => withData(api.post('/announcements', data)),
  update: (id, data) => withData(api.put(`/announcements/${id}`, data)),
  publish: (id) => withData(api.post(`/announcements/${id}/publish`)),
  unpublish: (id) => withData(api.post(`/announcements/${id}/unpublish`)),
  remove: (id) => api.delete(`/announcements/${id}`),
}
