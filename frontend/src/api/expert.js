import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const expertApi = {
  evaluate: (symptomIds) =>
    api.post('/expert-system/evaluate', { symptom_ids: symptomIds }).then((r) => r.data),

  diseases: (params) => api.get('/knowledge-base/diseases', { params }),
  symptoms: (params) => api.get('/knowledge-base/symptoms', { params }),
  expertRules: (params) => api.get('/knowledge-base/expert-rules', { params }),

  createDisease: (data) => withData(api.post('/knowledge-base/diseases', data)),
  updateDisease: (id, data) => withData(api.put(`/knowledge-base/diseases/${id}`, data)),
  removeDisease: (id) => api.delete(`/knowledge-base/diseases/${id}`),

  createSymptom: (data) => withData(api.post('/knowledge-base/symptoms', data)),
  updateSymptom: (id, data) => withData(api.put(`/knowledge-base/symptoms/${id}`, data)),
  removeSymptom: (id) => api.delete(`/knowledge-base/symptoms/${id}`),

  createExpertRule: (data) => withData(api.post('/knowledge-base/expert-rules', data)),
  updateExpertRule: (id, data) =>
    withData(api.put(`/knowledge-base/expert-rules/${id}`, data)),
  removeExpertRule: (id) => api.delete(`/knowledge-base/expert-rules/${id}`),

  auditLogs: (params) => api.get('/expert-system/audit-logs', { params }),
}