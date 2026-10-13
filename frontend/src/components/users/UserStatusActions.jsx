import { useState } from 'react'
import { toast } from 'sonner'
import { Ban, ShieldCheck, UserX } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import ConfirmDialog from '../ui/ConfirmDialog.jsx'
import { userApi } from '../../api/users.js'
import { stepUp } from '../../api/auth.js'

const STATUS_ACTIONS = {
  active: ['suspend', 'disable'],
  suspended: ['activate'],
  disabled: ['activate'],
  pending: ['activate'],
}

const LABELS = {
  suspend: 'Suspend',
  disable: 'Disable',
  activate: 'Activate',
}

export default function UserStatusActions({ user, onChanged, disabled = false }) {
  const [action, setAction] = useState(null)
  const [reason, setReason] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)

  const status = user.account_status ?? 'active'
  const actions = STATUS_ACTIONS[status] ?? []

  const close = () => {
    setAction(null)
    setReason('')
    setPassword('')
    setErrors({})
  }

  const handleSensitive = async () => {
    setErrors({})
    if (!password) {
      setErrors({ password: 'Enter your account password to confirm.' })
      return
    }
    setBusy(true)
    try {
      const grant = await stepUp({ action: 'users.suspend', password })
      const stepUpToken = grant.step_up_token
      if (action === 'suspend') {
        await userApi.suspend(user.id, { reason: reason.trim() || undefined, stepUpToken })
        toast.success(`${user.name} has been suspended.`)
      } else {
        await userApi.disable(user.id, { stepUpToken })
        toast.success(`${user.name} has been disabled.`)
      }
      close()
      onChanged?.()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setBusy(false)
    }
  }

  const handleActivate = async () => {
    setBusy(true)
    try {
      await userApi.activate(user.id)
      toast.success(`${user.name} has been activated.`)
      close()
      onChanged?.()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (actions.length === 0) return null

  return (
    <>
      <div className="flex justify-end gap-1">
        {actions.includes('activate') ? (
          <button
            type="button"
            aria-label={`Activate ${user.name}`}
            disabled={disabled}
            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-emerald-50 hover:text-emerald-600 disabled:opacity-50"
            onClick={() => setAction('activate')}
          >
            <ShieldCheck className="size-4" aria-hidden />
          </button>
        ) : null}
        {actions.includes('suspend') ? (
          <button
            type="button"
            aria-label={`Suspend ${user.name}`}
            disabled={disabled}
            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-amber-50 hover:text-amber-600 disabled:opacity-50"
            onClick={() => setAction('suspend')}
          >
            <UserX className="size-4" aria-hidden />
          </button>
        ) : null}
        {actions.includes('disable') ? (
          <button
            type="button"
            aria-label={`Disable ${user.name}`}
            disabled={disabled}
            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
            onClick={() => setAction('disable')}
          >
            <Ban className="size-4" aria-hidden />
          </button>
        ) : null}
      </div>

      <ConfirmDialog
        open={action === 'activate'}
        onClose={close}
        onConfirm={handleActivate}
        title={`Activate ${user.name}?`}
        message="The account will be able to sign in again immediately."
        confirmLabel="Activate"
        danger={false}
        loading={busy}
      />

      <Modal
        open={action === 'suspend' || action === 'disable'}
        onClose={close}
        title={`${LABELS[action] ?? ''} ${user.name}`}
        description={
          action === 'suspend'
            ? 'The account will be suspended and signed out of every device.'
            : 'The account will be disabled and signed out of every device.'
        }
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={close} disabled={busy}>
              Cancel
            </Button>
            <Button variant="danger" onClick={handleSensitive} loading={busy}>
              {LABELS[action]}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          {errors.form ? (
            <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
              {errors.form}
            </div>
          ) : null}
          {action === 'suspend' ? (
            <Field label="Reason" htmlFor="suspend-reason" hint="Optional — shown to administrators.">
              <Input
                id="suspend-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="e.g. Policy violation"
              />
            </Field>
          ) : null}
          <div className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
            This is a sensitive action. Confirm your identity with your account password.
          </div>
          <Field label="Your password" htmlFor="suspend-password" required error={errors.password}>
            <Input
              id="suspend-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              invalid={Boolean(errors.password)}
            />
          </Field>
        </div>
      </Modal>
    </>
  )
}
