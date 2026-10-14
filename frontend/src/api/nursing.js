import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const wardApi = {
  list: (params) => api.get('/wards', { params }),
  get: (id) => withData(api.get(`/wards/${id}`)),
  create: (data) => withData(api.post('/wards', data)),
  update: (id, data) => withData(api.put(`/wards/${id}`, data)),
  remove: (id) => api.delete(`/wards/${id}`),
}

export const roomApi = {
  list: (params) => api.get('/rooms', { params }),
  get: (id) => withData(api.get(`/rooms/${id}`)),
  create: (data) => withData(api.post('/rooms', data)),
  update: (id, data) => withData(api.put(`/rooms/${id}`, data)),
  remove: (id) => api.delete(`/rooms/${id}`),
}

export const bedApi = {
  list: (params) => api.get('/beds', { params }),
  get: (id) => withData(api.get(`/beds/${id}`)),
  create: (data) => withData(api.post('/beds', data)),
  update: (id, data) => withData(api.put(`/beds/${id}`, data)),
  setStatus: (id, status) => withData(api.post(`/beds/${id}/status`, { status })),
  remove: (id) => api.delete(`/beds/${id}`),
}

export const admissionApi = {
  list: (params) => api.get('/admissions', { params }),
  get: (id) => withData(api.get(`/admissions/${id}`)),
  create: (data) => withData(api.post('/admissions', data)),
  update: (id, data) => withData(api.put(`/admissions/${id}`, data)),
  discharge: (id, data) => withData(api.post(`/admissions/${id}/discharge`, data ?? {})),
  transfer: (id, data) => withData(api.post(`/admissions/${id}/transfer`, data)),
  cancel: (id, data) => withData(api.post(`/admissions/${id}/cancel`, data ?? {})),
}

export const assignmentApi = {
  list: (params) => api.get('/assignments', { params }),
  get: (id) => withData(api.get(`/assignments/${id}`)),
  create: (data) => withData(api.post('/assignments', data)),
  end: (id, data) => withData(api.post(`/assignments/${id}/end`, data ?? {})),
  myPatients: () => withData(api.get('/nursing/my-patients')),
}

export const nursingTaskApi = {
  list: (params) => api.get('/nursing-tasks', { params }),
  get: (id) => withData(api.get(`/nursing-tasks/${id}`)),
  create: (data) => withData(api.post('/nursing-tasks', data)),
  update: (id, data) => withData(api.put(`/nursing-tasks/${id}`, data)),
  complete: (id, data) => withData(api.post(`/nursing-tasks/${id}/complete`, data ?? {})),
  remove: (id) => api.delete(`/nursing-tasks/${id}`),
}

export const medicationAdministrationApi = {
  list: (params) => api.get('/medication-administrations', { params }),
  get: (id) => withData(api.get(`/medication-administrations/${id}`)),
  create: (data) => withData(api.post('/medication-administrations', data)),
  administer: (id, data) =>
    withData(api.post(`/medication-administrations/${id}/administer`, data ?? {})),
  setStatus: (id, status, notes) =>
    withData(
      api.post(`/medication-administrations/${id}/status`, { status, notes: notes ?? null }),
    ),
  remove: (id) => api.delete(`/medication-administrations/${id}`),
}
