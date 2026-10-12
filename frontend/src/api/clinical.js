import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const medicalRecordApi = {
  list: (params) => api.get('/medical-records', { params }),
  get: (id) => withData(api.get(`/medical-records/${id}`)),
  create: (data) => withData(api.post('/medical-records', data)),
  update: (id, data) => withData(api.put(`/medical-records/${id}`, data)),
  remove: (id) => api.delete(`/medical-records/${id}`),
}

export const consultationApi = {
  list: (params) => api.get('/consultations', { params }),
  get: (id) => withData(api.get(`/consultations/${id}`)),
  create: (data) => withData(api.post('/consultations', data)),
  update: (id, data) => withData(api.put(`/consultations/${id}`, data)),
  remove: (id) => api.delete(`/consultations/${id}`),
}

export const diagnosisApi = {
  list: (params) => api.get('/diagnoses', { params }),
  get: (id) => withData(api.get(`/diagnoses/${id}`)),
  create: (data) => withData(api.post('/diagnoses', data)),
  update: (id, data) => withData(api.put(`/diagnoses/${id}`, data)),
  remove: (id) => api.delete(`/diagnoses/${id}`),
}

export const vitalSignApi = {
  list: (params) => api.get('/vital-signs', { params }),
  get: (id) => withData(api.get(`/vital-signs/${id}`)),
  create: (data) => withData(api.post('/vital-signs', data)),
  update: (id, data) => withData(api.put(`/vital-signs/${id}`, data)),
  remove: (id) => api.delete(`/vital-signs/${id}`),
}