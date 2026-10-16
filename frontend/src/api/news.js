import { api } from './client.js'

export const newsApi = {
  list: (params) => api.get('/news', { params }),
  get: (id) => api.get(`/news/${id}`),
  read: (id) => api.post(`/news/${id}/read`),
  markAllRead: () => api.post('/news/read-all'),
  unreadCount: () => api.get('/news/unread-count').then((r) => r.unread_count),
}
