import { api } from './client.js'

export const conversationApi = {
  list: (params) => api.get('/conversations', { params }),
  recipients: () => api.get('/conversations/recipients').then((r) => r.data),
  create: (data) => api.post('/conversations', data).then((r) => r.data),
  get: (id) => api.get(`/conversations/${id}`).then((r) => r.data),
  messages: (id, params) => api.get(`/conversations/${id}/messages`, { params }),
  send: (id, body) => api.post(`/conversations/${id}/messages`, { body }).then((r) => r.data),
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

export const telegramApi = {
  status: () => api.get('/telegram/status').then((r) => r.data),
  link: () => api.post('/telegram/link').then((r) => r.data),
  unlink: () => api.delete('/telegram/link'),
  registerWebhook: () => api.post('/telegram/webhook/register').then((r) => r.data),
  groupConfig: () => api.get('/telegram/group-config').then((r) => r.data),
  updateGroupConfig: (data) => api.put('/telegram/group-config', data).then((r) => r.data),
}
