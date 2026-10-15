import { api, httpClient } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const attendanceApi = {
  list: (params) => api.get('/attendance', { params }),
  today: () => api.get('/attendance/today'),
  get: (id) => withData(api.get(`/attendance/${id}`)),
  create: (data) => withData(api.post('/attendance', data)),
  update: (id, data) => withData(api.put(`/attendance/${id}`, data)),
  correct: (id, data) => withData(api.post(`/attendance/${id}/correct`, data)),
  approve: (id) => withData(api.post(`/attendance/${id}/approve`)),
  remove: (id) => api.delete(`/attendance/${id}`),
  clockIn: () => withData(api.post('/attendance/clock-in')),
  clockOut: () => withData(api.post('/attendance/clock-out')),
  scan: (data) => withData(api.post('/attendance/scan', data)),

  async exportCsv(params) {
    const response = await httpClient.get('/attendance/export', { params, responseType: 'blob' })
    return response.data
  },

  sessions: {
    list: (params) => api.get('/attendance/sessions', { params }),
    open: (data) => withData(api.post('/attendance/sessions', data)),
    get: (id) => withData(api.get(`/attendance/sessions/${id}`)),
    token: (id) => withData(api.get(`/attendance/sessions/${id}/token`)),
    close: (id) => withData(api.post(`/attendance/sessions/${id}/close`)),
  },
}
