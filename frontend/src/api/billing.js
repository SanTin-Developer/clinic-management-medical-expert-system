import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const invoiceApi = {
  list: (params) => api.get('/invoices', { params }),
  get: (id) => withData(api.get(`/invoices/${id}`)),
  create: (data) => withData(api.post('/invoices', data)),
  update: (id, data) => withData(api.put(`/invoices/${id}`, data)),
  recordPayment: (id, data) => withData(api.post(`/invoices/${id}/payments`, data)),
  cancel: (id) => withData(api.post(`/invoices/${id}/cancel`)),
  remove: (id) => api.delete(`/invoices/${id}`),
}