import { useState } from 'react'
import { toast } from 'sonner'
import { Check, X } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import { staffApprovalApi } from '../../api/staffApprovals.js'
import { stepUp } from '../../api/auth.js'
import { useAuth } from '../../hooks/useAuth.js'

/**
 * Approve / Reject actions for a staff account awaiting approval. Both actions
 * are sensitive and require server-side step-up (the admin's password and, if
 * enrolled, an authenticator code) before the decision is recorded.
 */
export default function StaffApprovalActions({ user, onChanged, disabled = false }) {
  const { user: currentUser } = useAuth()
  const [action, setAction] = useState(null) // 'approve' | 'reject'
  const [reason, setReason] = useState('')
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [needsCode, setNeedsCode] = useState(false)
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)

  const adminHasTwoFactor = Boolean(currentUser?.two_factor_enabled)

  const close = () => {
    setAction(null)
    setReason('')
    setPassword('')
    setCode('')
    setNeedsCode(false)
    setErrors({})
  }

  const handleConfirm = async () => {
    setErrors({})
    if (!password) {
      setErrors({ password: 'Enter your account password to confirm.' })
      return
    }
    if (needsCode && !code.trim()) {
      setErrors({ code: 'Enter the code from your authenticator app.' })
      return
    }
    if (action === 'reject' && reason.trim().length < 3) {
      setErrors({ reason: 'Provide a reason of at least 3 characters.' })
      return
    }

    setBusy(true)
    try {
      const grant = await stepUp({
        action: 'users.approve',
        password,
        ...(code.trim() ? { two_factor_code: code.trim() } : {}),
      })
      const stepUpToken = grant.step_up_token

      if (action === 'approve') {
        await staffApprovalApi.approve(user.id, stepUpToken)
        toast.success(`${user.name} has been approved.`)
      } else {
        await staffApprovalApi.reject(user.id, { reason: reason.trim(), stepUpToken })
        toast.success(`${user.name} has been rejected.`)
      }
      close()
      onChanged?.()
    } catch (err) {
      // The server asks for a second factor: reveal the code field and retry.
      if (err?.payload?.two_factor_required) {
        setNeedsCode(true)
        setErrors({ form: err.message || 'Enter the code from your authenticator app to continue.' })
      } else {
        setErrors({ form: err.message })
      }
    } finally {
      setBusy(false)
    }
  }

  const isApprove = action === 'approve'

  return (
    <>
      <div className="flex justify-end gap-1">
        <button
          type="button"
          aria-label={`Approve ${user.name}`}
          disabled={disabled}
          className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-emerald-50 hover:text-emerald-600 disabled:opacity-50"
          onClick={() => setAction('approve')}
        >
          <Check className="size-4" aria-hidden />
        </button>
        <button
          type="button"
          aria-label={`Reject ${user.name}`}
          disabled={disabled}
          className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
          onClick={() => setAction('reject')}
        >
          <X className="size-4" aria-hidden />
        </button>
      </div>

      <Modal
        open={action !== null}
        onClose={close}
        title={`${isApprove ? 'Approve' : 'Reject'} ${user.name}`}
        description={
          isApprove
            ? 'The account will become active and able to sign in.'
            : 'The account will be rejected and blocked from signing in.'
        }
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={close} disabled={busy}>
              Cancel
            </Button>
            <Button variant={isApprove ? 'primary' : 'danger'} onClick={handleConfirm} loading={busy}>
              {isApprove ? 'Approve' : 'Reject'}
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
          {!isApprove ? (
            <Field label="Reason" htmlFor="reject-reason" required error={errors.reason} hint="Recorded in the audit log.">
              <Input
                id="reject-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="e.g. Credential could not be verified"
                invalid={Boolean(errors.reason)}
              />
            </Field>
          ) : null}
          <div className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
            This is a sensitive action. Confirm your identity with your account password
            {adminHasTwoFactor ? ' and a code from your authenticator app.' : '.'}
          </div>
          <Field label="Your password" htmlFor="approval-password" required error={errors.password}>
            <Input
              id="approval-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              invalid={Boolean(errors.password)}
            />
          </Field>
          {needsCode || adminHasTwoFactor ? (
            <Field
              label="Authenticator code"
              htmlFor="approval-code"
              required={needsCode}
              error={errors.code}
              hint={needsCode ? undefined : 'Only required if prompted.'}
            >
              <Input
                id="approval-code"
                inputMode="text"
                autoComplete="one-time-code"
                placeholder="123456"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                invalid={Boolean(errors.code)}
              />
            </Field>
          ) : null}
        </div>
      </Modal>
    </>
  )
}
