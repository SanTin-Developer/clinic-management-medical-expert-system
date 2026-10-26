import { api } from './client.js'

export function login(credentials) {
  return api.post('/auth/login', credentials)
}

export function verifyOtp(payload) {
  return api.post('/auth/otp/verify', payload)
}

export function verifyTwoFactor(payload) {
  return api.post('/auth/2fa/verify', payload)
}

export function twoFactorStatus() {
  return api.get('/auth/2fa/status')
}

export function enrollTwoFactor(password) {
  return api.post('/auth/2fa/enroll', { password })
}

export function confirmTwoFactor(code) {
  return api.post('/auth/2fa/confirm', { code })
}

export function disableTwoFactor(password) {
  return api.delete('/auth/2fa', { data: { password } })
}

export function regenerateRecoveryCodes(password) {
  return api.post('/auth/2fa/recovery-codes', { password })
}

export function changePassword(payload) {
  return api.put('/auth/password', payload)
}

export function requestEmailChange(payload) {
  return api.post('/auth/email/change', payload)
}

export function confirmEmailChange(code) {
  return api.post('/auth/email/change/confirm', { code })
}

export function stepUp(payload) {
  return api.post('/auth/step-up', payload)
}

export function securityEvents() {
  return api.get('/auth/security-events').then((res) => res.data ?? [])
}

export function lookupInvitation(token) {
  return api.post('/auth/invitation/lookup', { token })
}

export function acceptInvitation(payload) {
  return api.post('/auth/invitation/accept', payload)
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

export function listSessions() {
  return api.get('/auth/sessions').then((res) => res.data ?? [])
}

export function revokeSession(id) {
  return api.delete(`/auth/sessions/${id}`)
}

export function revokeOtherSessions() {
  return api.delete('/auth/sessions/others')
}