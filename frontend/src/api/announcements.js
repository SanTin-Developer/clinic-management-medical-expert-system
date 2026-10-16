import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

const hasPosterFile = (data) => data?.poster instanceof File

function toFormData(data) {
  const formData = new FormData()
  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined) return
    if (key === 'poster') {
      if (value instanceof File) formData.append('poster', value)
      return
    }
    if (value === null) {
      formData.append(key, '')
      return
    }
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
  })
  return formData
}

export const announcementApi = {
  list: (params) => api.get('/announcements', { params }),
  get: (id) => withData(api.get(`/announcements/${id}`)),
  create: (data) =>
    withData(api.post('/announcements', hasPosterFile(data) ? toFormData(data) : data)),
  update: (id, data) =>
    hasPosterFile(data)
      ? withData(api.post(`/announcements/${id}`, toFormData({ ...data, _method: 'PUT' })))
      : withData(api.put(`/announcements/${id}`, data)),
  publish: (id) => withData(api.post(`/announcements/${id}/publish`)),
  unpublish: (id) => withData(api.post(`/announcements/${id}/unpublish`)),
  schedule: (id, data) => withData(api.post(`/announcements/${id}/schedule`, data)),
  archive: (id) => withData(api.post(`/announcements/${id}/archive`)),
  restore: (id) => withData(api.post(`/announcements/${id}/restore`)),
  readership: (id) => withData(api.get(`/announcements/${id}/readership`)),
  audit: (id, params) => api.get(`/announcements/${id}/audit`, { params }),
  remove: (id) => api.delete(`/announcements/${id}`),
}
