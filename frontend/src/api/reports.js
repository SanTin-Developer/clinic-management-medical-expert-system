import { api, httpClient } from './client.js'

export const reportApi = {
  summary: () => api.get('/reports/summary').then((r) => r.data),
  appointments: (params) => api.get('/reports/appointments', { params }).then((r) => r.data),
  revenue: (params) => api.get('/reports/revenue', { params }).then((r) => r.data),
  inventory: () => api.get('/reports/inventory').then((r) => r.data),
}

const VALID_TYPES = ['appointments', 'revenue', 'inventory']

export async function exportReport(type, format, params = {}) {
  if (!VALID_TYPES.includes(type)) {
    throw new Error(`Unsupported report type: ${type}`)
  }
  if (!['export', 'pdf'].includes(format)) {
    throw new Error(`Unsupported report format: ${format}`)
  }
  const res = await httpClient.get(`/reports/${type}/${format}`, {
    params,
    responseType: 'blob',
  })

  const disposition = res.headers['content-disposition'] ?? ''
  const match = disposition.match(/filename="?(.+?)"?;?$/i)
  const filename = match?.[1] ?? `${type}-report.${format === 'pdf' ? 'pdf' : 'csv'}`

  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}