import { api } from './client.js'

export const staffInvitationApi = {
  list: (params) => api.get('/staff/invitations', { params }),
  create: (data) => api.post('/staff/invitations', data).then((r) => r.invitation),
  resend: (id) => api.post(`/staff/invitations/${id}/resend`),
  revoke: (id) => api.delete(`/staff/invitations/${id}`),
}

export function invitationStatus(invitation) {
  if (invitation.accepted_at) return 'accepted'
  if (invitation.revoked_at) return 'revoked'
  if (invitation.expires_at && new Date(invitation.expires_at).getTime() < Date.now()) {
    return 'expired'
  }
  return 'pending'
}

export const INVITATION_STATUS_META = {
  pending: { label: 'Pending', tone: 'amber' },
  accepted: { label: 'Accepted', tone: 'green' },
  revoked: { label: 'Revoked', tone: 'gray' },
  expired: { label: 'Expired', tone: 'red' },
}
