import { api } from './client.js'

export function login(credentials) {
  return api.post('/auth/login', credentials)
}

export function verifyOtp(payload) {
  return api.post('/auth/otp/verify', payload)
}

export function resendOtp(challengeToken) {
  return api.post('/auth/otp/resend', { challenge_token: challengeToken })
}

export function forgotPassword(email) {
  return api.post('/auth/forgot-password', { email })
}

export function verifyResetCode(payload) {
  return api.post('/auth/reset-password/verify-code', payload)
}

export function resetPassword(payload) {
  return api.post('/auth/reset-password', payload)
}

export function verifyEmailUrl(url) {
  return api.get(url)
}

export function resendVerificationEmail() {
  return api.post('/auth/email/verification-notification')
}

export function me() {
  return api.get('/auth/me')
}

export function logout() {
  return api.post('/auth/logout')
}

export function listDevices() {
  return api.get('/auth/devices').then((res) => res.data ?? [])
}

export function revokeDevice(id) {
  return api.delete(`/auth/devices/${id}`)
}

export function revokeAllDevices() {
  return api.delete('/auth/devices')
}