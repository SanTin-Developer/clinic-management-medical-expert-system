import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Select from '../ui/Select.jsx'
import { staffApi } from '../../api/staff.js'

export default function StaffLinkUserModal({ open, onClose, staff, onSaved }) {
  const [userId, setUserId] = useState('')
  const [availableUsers, setAvailableUsers] = useState([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!open || !staff) return

    setLoading(true)
    setError('')
    setUserId(staff.user_id ? String(staff.user_id) : '')

    staffApi.availableUsers(staff.id)
      .then((users) => {
        setAvailableUsers(
          users.map((u) => ({
            value: String(u.id),
            label: `${u.name} (@${u.username || 'user'}) - ${u.email} [${u.account_status}]`,
          }))
        )
      })
      .catch((err) => {
        setError(err.message || 'Failed to load available users.')
      })
      .finally(() => setLoading(false))
  }, [open, staff])

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (!userId) {
      setError('Please select a user account to link.')
      return
    }

    setSaving(true)
    setError('')
    try {
      await staffApi.linkUser(staff.id, Number(userId))
      toast.success('User account successfully linked to this staff profile.')
      onSaved?.()
      onClose()
    } catch (err) {
      const msg = err.payload?.errors?.user_id?.[0] || err.message || 'Failed to link user account.'
      setError(msg)
      toast.error(msg)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Link System User Account"
      description={`Link a system login account to ${staff?.full_name || staff?.staff_number}. This enables application access for this staff member without creating duplicate directory records.`}
      size="md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        {loading ? (
          <p className="text-sm text-ink-muted py-4 text-center">Loading available accounts…</p>
        ) : availableUsers.length === 0 ? (
          <div className="rounded-lg border border-line bg-canvas p-4 text-sm text-ink-muted">
            <p className="font-medium text-ink">No unlinked user accounts found.</p>
            <p className="mt-1">
              All existing user accounts are already linked to other staff profiles. You can invite a new staff member from Staff Invitations.
            </p>
          </div>
        ) : (
          <Field
            label="Select system user account"
            required
            error={error}
            hint="Only accounts not currently linked to other staff profiles are listed."
          >
            <Select
              value={userId}
              onChange={(event) => setUserId(event.target.value)}
            >
              {[{ value: '', label: 'Select an account…' }, ...availableUsers].map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </Select>
          </Field>
        )}

        <div className="flex justify-end gap-3 pt-3 border-t border-line">
          <Button type="button" variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving || loading || availableUsers.length === 0}>
            {saving ? 'Linking…' : 'Link account'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
