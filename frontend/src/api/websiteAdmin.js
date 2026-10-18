import { api } from './client.js'

const withData = (promise) => promise.then((res) => res.data)

function toFormData(data) {
  const formData = new FormData()
  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined) return
    if (value instanceof File) {
      formData.append(key, value)
      return
    }
    if (value === null) {
      formData.append(key, '')
      return
    }
    if (Array.isArray(value)) {
      value.forEach((entry) => formData.append(`${key}[]`, entry))
      return
    }
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
  })
  return formData
}

const create = (path, payload) => withData(api.post(path, toFormData(payload)))
const update = (path, payload) =>
  withData(api.post(path, toFormData({ ...payload, _method: 'PUT' })))

/**
 * Authenticated admin endpoints for the Website Management CMS.
 */
export const websiteAdminApi = {
  dashboard: () => api.get('/website/admin/dashboard'),

  getSettings: () => api.get('/website/admin/settings'),
  updateSettings: (payload) =>
    withData(api.post('/website/admin/settings', toFormData({ ...payload, _method: 'PUT' }))),

  listPages: (params) => api.get('/website/admin/pages', { params }),
  getPage: (id) => withData(api.get(`/website/admin/pages/${id}`)),
  createPage: (payload) => create('/website/admin/pages', payload),
  updatePage: (id, payload) => update(`/website/admin/pages/${id}`, payload),
  deletePage: (id) => api.delete(`/website/admin/pages/${id}`),
  publishPage: (id) => withData(api.post(`/website/admin/pages/${id}/publish`)),
  unpublishPage: (id) => withData(api.post(`/website/admin/pages/${id}/unpublish`)),

  listServices: (params) => api.get('/website/admin/services', { params }),
  getService: (id) => withData(api.get(`/website/admin/services/${id}`)),
  createService: (payload) => create('/website/admin/services', payload),
  updateService: (id, payload) => update(`/website/admin/services/${id}`, payload),
  deleteService: (id) => api.delete(`/website/admin/services/${id}`),

  listTeam: (params) => api.get('/website/admin/team', { params }),
  getTeam: (id) => withData(api.get(`/website/admin/team/${id}`)),
  createTeam: (payload) => create('/website/admin/team', payload),
  updateTeam: (id, payload) => update(`/website/admin/team/${id}`, payload),
  deleteTeam: (id) => api.delete(`/website/admin/team/${id}`),

  listGallery: (params) => api.get('/website/admin/gallery', { params }),
  getGallery: (id) => withData(api.get(`/website/admin/gallery/${id}`)),
  createGallery: (payload) => create('/website/admin/gallery', payload),
  updateGallery: (id, payload) => update(`/website/admin/gallery/${id}`, payload),
  deleteGallery: (id) => api.delete(`/website/admin/gallery/${id}`),

  listArticles: (params) => api.get('/website/admin/articles', { params }),
  getArticle: (id) => withData(api.get(`/website/admin/articles/${id}`)),
  createArticle: (payload) => create('/website/admin/articles', payload),
  updateArticle: (id, payload) => update(`/website/admin/articles/${id}`, payload),
  deleteArticle: (id) => api.delete(`/website/admin/articles/${id}`),
  publishArticle: (id) => withData(api.post(`/website/admin/articles/${id}/publish`)),
  unpublishArticle: (id) => withData(api.post(`/website/admin/articles/${id}/unpublish`)),

  listMedia: (params) => api.get('/website/admin/media', { params }),
  uploadMedia: (payload) => create('/website/admin/media', payload),
  deleteMedia: (id) => api.delete(`/website/admin/media/${id}`),

  listContactMessages: (params) => api.get('/website/admin/contact-messages', { params }),
  getContactMessage: (id) => withData(api.get(`/website/admin/contact-messages/${id}`)),
  markContactMessageRead: (id) => withData(api.post(`/website/admin/contact-messages/${id}/read`)),
  markContactMessageUnread: (id) => withData(api.post(`/website/admin/contact-messages/${id}/unread`)),
  deleteContactMessage: (id) => api.delete(`/website/admin/contact-messages/${id}`),
}
