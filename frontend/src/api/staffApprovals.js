import { api } from './client.js'

function stepUpHeaders(token) {
  return token ? { headers: { 'X-Step-Up-Token': token } } : {}
}

export const staffApprovalApi = {
  list: (params) => api.get('/staff/approvals', { params }),
  approve: (id, stepUpToken) =>
    api.post(`/users/${id}/approve`, {}, stepUpHeaders(stepUpToken)),
  reject: (id, { reason, stepUpToken } = {}) =>
    api.post(`/users/${id}/reject`, { reason }, stepUpHeaders(stepUpToken)),
}
