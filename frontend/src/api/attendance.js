import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const attendanceApi = {
  list: (params) => api.get('/attendance', { params }),
  today: () => api.get('/attendance/today'),
  get: (id) => withData(api.get(`/attendance/${id}`)),
  create: (data) => withData(api.post('/attendance', data)),
  update: (id, data) => withData(api.put(`/attendance/${id}`, data)),
  approve: (id) => withData(api.post(`/attendance/${id}/approve`)),
  remove: (id) => api.delete(`/attendance/${id}`),
  clockIn: () => withData(api.post('/attendance/clock-in')),
  clockOut: () => withData(api.post('/attendance/clock-out')),
}
