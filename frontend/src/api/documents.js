import { api, httpClient, API_BASE_URL } from './client.js'

export const documentApi = {
  list: (patientId) =>
    api.get(`/patients/${patientId}/documents`).then((r) => r.data ?? []),
  upload: (patientId, formData) =>
    api
      .post(`/patients/${patientId}/documents`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data),
  remove: (id) => api.delete(`/documents/${id}`),
}

export async function downloadDocument(id, fallbackName = 'document') {
  const res = await httpClient.get(`/documents/${id}/download`, {
    responseType: 'blob',
  })
  saveBlob(res.data, fallbackName)
}

export function documentDownloadUrl(id) {
  return `${API_BASE_URL}/documents/${id}/download`
}

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