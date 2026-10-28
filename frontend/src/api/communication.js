import { api } from './client.js'

export const conversationApi = {
  list: (params) => api.get('/conversations', { params }),
  recipients: () => api.get('/conversations/recipients').then((r) => r.data),
  create: (data) => api.post('/conversations', data).then((r) => r.data),
  get: (id) => api.get(`/conversations/${id}`).then((r) => r.data),
  messages: (id, params) => api.get(`/conversations/${id}/messages`, { params }),
  send: (id, body, file, onUploadProgress) => {
    if (file) {
      const formData = new FormData()
      if (typeof body === 'string' && body.trim()) {
        formData.append('body', body.trim())
      }
      formData.append('attachment', file)
      return api
        .post(`/conversations/${id}/messages`, formData, {
          onUploadProgress,
          timeout: 300_000,
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((r) => r.data)
    }
    return api.post(`/conversations/${id}/messages`, { body }).then((r) => r.data)
  },
  updateMessage: (id, messageId, body) =>
    api.put(`/conversations/${id}/messages/${messageId}`, { body }).then((r) => r.data),
  deleteMessage: (id, messageId, scope) =>
    api.delete(`/conversations/${id}/messages/${messageId}`, { data: { scope } }).then((r) => r.data),
  bulkDeleteMessages: (id, messageIds, scope) =>
    api.post(`/conversations/${id}/messages/bulk-delete`, { message_ids: messageIds, scope }).then((r) => r.data),
  messageDelivery: (id, messageId) =>
    api.get(`/conversations/${id}/messages/${messageId}/delivery`).then((r) => r.data),
  markRead: (id, messageId) =>
    api.post(`/conversations/${id}/read`, messageId ? { message_id: messageId } : {}),
  addParticipants: (id, participantIds) =>
    api.post(`/conversations/${id}/participants`, { participant_ids: participantIds }).then((r) => r.data),
  removeParticipant: (id, userId) =>
    api.delete(`/conversations/${id}/participants/${userId}`).then((r) => r.data),
}

export const notificationApi = {
  list: (params) => api.get('/notifications', { params }),
  unreadCount: () => api.get('/notifications/unread-count').then((r) => r.unread_count),
  markRead: (id) => api.post(`/notifications/${id}/read`).then((r) => r.unread_count),
  markAllRead: () => api.post('/notifications/read-all').then((r) => r.unread_count),
  remove: (id) => api.delete(`/notifications/${id}`),
  preferences: () => api.get('/notification-preferences').then((r) => r.data),
  updatePreferences: (data) => api.put('/notification-preferences', data).then((r) => r.data),
}

export const systemAlertApi = {
  list: (params) => api.get('/system-alerts', { params }),
  unreadCount: () => api.get('/system-alerts/unread-count').then((r) => r.unread_count),
  summary: () => api.get('/system-alerts/summary').then((r) => r.data),
  markRead: (id) => api.post(`/system-alerts/${id}/read`).then((r) => r.data),
  markAllRead: () => api.post('/system-alerts/read-all').then((r) => r.count),
  acknowledge: (id) => api.post(`/system-alerts/${id}/acknowledge`).then((r) => r.data),
  resolve: (id) => api.post(`/system-alerts/${id}/resolve`).then((r) => r.data),
}

export const telegramApi = {
  status: () => api.get('/telegram/status').then((r) => r.data),
  link: () => api.post('/telegram/link').then((r) => r.data),
  unlink: () => api.delete('/telegram/link'),
  registerWebhook: () => api.post('/telegram/webhook/register').then((r) => r.data),
  groupConfig: () => api.get('/telegram/group-config').then((r) => r.data),
  updateGroupConfig: (data) => api.put('/telegram/group-config', data).then((r) => r.data),
}
