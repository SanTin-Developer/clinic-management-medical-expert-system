import { api } from './client.js'

const withData = (promise) => promise.then((res) => {
  // Some endpoints wrap the payload in { data } / { result }; the ledger
  // endpoint returns its fields at the top level.
  const data = res?.data?.data ?? res?.data?.result ?? res?.data ?? res
  return data === undefined ? {} : data
})

export const inpatientBillingApi = {
  ledger: (admissionId) => withData(api.get(`/admissions/${admissionId}/billing`)),
  generateRoomCharges: (admissionId) => withData(api.post(`/admissions/${admissionId}/billing/room-charges`)),
  addCharge: (admissionId, data) => withData(api.post(`/admissions/${admissionId}/billing/charges`, data)),
  generateDailyInvoice: (admissionId, data = {}) => withData(api.post(`/admissions/${admissionId}/billing/daily-invoice`, data)),
  recordPayment: (admissionId, data) => withData(api.post(`/admissions/${admissionId}/billing/payments`, data)),
  dischargeReconciliation: (admissionId, data = {}) => withData(api.post(`/admissions/${admissionId}/billing/discharge-reconciliation`, data)),
  voidCharge: (chargeId, reason) => withData(api.post(`/admission-charges/${chargeId}/void`, { reason })),
}

export const billableServiceApi = {
  list: (params) => api.get('/billable-services', { params }).then((r) => r.data),
  create: (data) => withData(api.post('/billable-services', data)),
  update: (id, data) => withData(api.put(`/billable-services/${id}`, data)),
  remove: (id) => api.delete(`/billable-services/${id}`),
}
