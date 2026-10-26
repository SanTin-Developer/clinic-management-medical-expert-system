import { api, httpClient } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const invoiceApi = {
  list: (params) => api.get('/invoices', { params }),
  get: (id) => withData(api.get(`/invoices/${id}`)),
  medicines: (params) => api.get('/invoices/medicines', { params }),
  create: (data) => withData(api.post('/invoices', data)),
  update: (id, data) => withData(api.put(`/invoices/${id}`, data)),
  recordPayment: (id, data) => withData(api.post(`/invoices/${id}/payments`, data)),
  cancel: (id) => withData(api.post(`/invoices/${id}/cancel`)),
  remove: (id) => api.delete(`/invoices/${id}`),
  downloadPdf: async (id) => {
    const response = await httpClient.get(`/invoices/${id}/pdf`, { responseType: 'blob' })
    const disposition = response.headers['content-disposition'] ?? ''
    const filename = disposition.match(/filename="?(.+?)"?;?$/i)?.[1] ?? `RoyalCare-Invoice-${id}.pdf`
    const url = URL.createObjectURL(response.data)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  },
}
