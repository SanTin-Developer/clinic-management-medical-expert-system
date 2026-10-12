import { api } from './client.js'

const withData = (promise) => promise.then((r) => r.data)

export const medicineApi = {
  list: (params) => api.get('/medicines', { params }),
  get: (id) => withData(api.get(`/medicines/${id}`)),
  create: (data) => withData(api.post('/medicines', data)),
  update: (id, data) => withData(api.put(`/medicines/${id}`, data)),
  remove: (id) => api.delete(`/medicines/${id}`),
}

export const inventoryApi = {
  list: (params) => api.get('/inventory-items', { params }),
  get: (id) => withData(api.get(`/inventory-items/${id}`)),
  create: (data) => withData(api.post('/inventory-items', data)),
  update: (id, data) => withData(api.put(`/inventory-items/${id}`, data)),
  remove: (id) => api.delete(`/inventory-items/${id}`),
}

export const stockMovementApi = {
  list: (params) => api.get('/stock-movements', { params }),
  create: (data) => withData(api.post('/stock-movements', data)),
}