import { api } from './client.js'

export function getUserPreferences() {
  return api.get('/user/preferences').then((res) => res.data ?? {})
}

export function updateUserPreferences(payload) {
  return api.put('/user/preferences', payload).then((res) => res.data ?? {})
}

export function updateUserProfile(payload) {
  return api.put('/user/preferences', payload).then((res) => res.data ?? {})
}

export function updateUserAvatar(file) {
  const formData = new FormData()
  formData.append('avatar', file)
  return api.post('/user/avatar', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then((res) => res.data ?? {})
}

export function removeUserAvatar() {
  return api.delete('/user/avatar').then((res) => res.data ?? {})
}

export function exportUserData() {
  return api.post('/user/data-export').then((res) => res.data ?? {})
}

export function deleteUserAccount(payload) {
  return api.delete('/user/account', { data: payload }).then((res) => res.data ?? {})
}
