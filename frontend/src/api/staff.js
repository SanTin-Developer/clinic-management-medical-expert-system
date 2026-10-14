import { api, httpClient } from './client.js'

function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export const staffApi = {
  list: (params) => api.get('/staff', { params }),
  get: (id) => api.get(`/staff/${id}`).then((r) => r.staff),
  create: (data, cvFile = null) => {
    if (cvFile) {
      const formData = new FormData()
      for (const [key, value] of Object.entries(data)) {
        if (value === undefined || value === null) continue
        formData.append(key, value)
      }
      formData.append('cv', cvFile)
      return api
        .post('/staff', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then((r) => r.staff)
    }
    return api.post('/staff', data).then((r) => r.staff)
  },
  update: (id, data) => api.put(`/staff/${id}`, data).then((r) => r.staff),
  remove: (id) => api.delete(`/staff/${id}`),

  // Compensation
  updateSalary: (id, data) => api.post(`/staff/${id}/salary`, data),

  // Account linking
  linkUser: (id, userId) => api.post(`/staff/${id}/link-user`, { user_id: userId }),
  unlinkUser: (id) => api.delete(`/staff/${id}/unlink-user`),

  // Dropdown data
  departments: () => api.get('/staff/departments').then((r) => r.departments ?? []),
  jobTitles: () => api.get('/staff/job-titles').then((r) => r.job_titles ?? []),
  availableUsers: (staffId) =>
    api.get('/staff/available-users', { params: staffId ? { staff_id: staffId } : {} }).then((r) => r.users ?? []),

  // Documents
  listDocuments: (staffId) => api.get(`/staff/${staffId}/documents`).then((r) => r.documents ?? []),
  uploadDocument: (staffId, formData) =>
    api.post(`/staff/${staffId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.document),
  deleteDocument: (docId) => api.delete(`/staff-documents/${docId}`),
  downloadDocument: async (docId, fallbackName = 'staff-document') => {
    try {
      const res = await httpClient.get(`/staff-documents/${docId}/download`, {
        responseType: 'blob',
      })
      const disposition = res.headers?.['content-disposition'] ?? ''
      const match = disposition.match(/filename="?(.+?)"?;?$/i)
      const filename = match?.[1] || fallbackName
      saveBlob(res.data, filename)
    } catch (err) {
      if (err.response?.data instanceof Blob) {
        try {
          const text = await err.response.data.text()
          const json = JSON.parse(text)
          if (json.message) {
            throw new Error(json.message)
          }
        } catch (parseErr) {
          if (parseErr.message && parseErr !== err) throw parseErr
        }
      }
      throw err
    }
  },
}
