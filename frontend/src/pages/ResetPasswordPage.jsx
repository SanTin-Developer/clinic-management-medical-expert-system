import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import { forgotPassword, verifyResetCode } from '../api/auth.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

const CODE_LENGTH = 6
const EMAIL_RE = /^\S+@\S+\.\S+$/

export default function ResetPasswordPage() {
  usePageTitle('Reset Password')
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const emailParam = searchParams.get('email') ?? ''

  const [email, setEmail] = useState(emailParam)
  const [editingEmail, setEditingEmail] = useState(false)
  const [emailError, setEmailError] = useState('')
  const [digits, setDigits] = useState(() => Array.from({ length: CODE_LENGTH }, () => ''))
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [resending, setResending] = useState(false)
  const inputRefs = useRef([])

  const currentEmail = email.trim()
  const setRef = (index) => (element) => {
    inputRefs.current[index] = element
  }

  useEffect(() => {
    inputRefs.current[0]?.focus()
  }, [])

  const submit = async (inputCode) => {
    if (inputCode.length !== CODE_LENGTH) return
    if (!currentEmail) {
      setEmailError('Enter the email you used to request the code.')
      return
    }
    if (!EMAIL_RE.test(currentEmail)) {
      setEmailError('Enter a valid email address.')
      return
    }

    setLoading(true)
    setErrors({})
    setEmailError('')
    try {
      const result = await verifyResetCode({ email: currentEmail, code: inputCode })
      navigate('/reset-password/new', {
        replace: true,
        state: { email: currentEmail, reset_token: result.reset_token },
      })
    } catch (err) {
      setErrors({ form: err.message })
      setDigits(Array.from({ length: CODE_LENGTH }, () => ''))
      inputRefs.current[0]?.focus()
    } finally {
      setLoading(false)
    }
  }

  const handleChange = (index, value) => {
    const digit = value.replace(/\D/g, '').slice(-1)
    const next = [...digits]
    next[index] = digit
    setDigits(next)

    if (digit && index < CODE_LENGTH - 1) {
      inputRefs.current[index + 1]?.focus()
    }
    if (digit && index === CODE_LENGTH - 1) {
      submit(next.join(''))
    }
  }

  const handleKeyDown = (index, event) => {
    if (event.key === 'Backspace' && !digits[index] && index > 0) {
      inputRefs.current[index - 1]?.focus()
    }
  }

  const handlePaste = (index, event) => {
    const pasted = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, CODE_LENGTH)
    if (!pasted) return
    event.preventDefault()
    const next = [...digits]
    pasted.split('').forEach((digit, offset) => {
      const at = index + offset
      if (at < CODE_LENGTH) next[at] = digit
    })
    setDigits(next)

    const filled = next.join('')
    if (filled.length >= CODE_LENGTH) {
      submit(filled)
    } else {
      inputRefs.current[Math.min(index + pasted.length, CODE_LENGTH - 1)]?.focus()
    }
  }

  const handleResend = async () => {
    if (!currentEmail) {
      setEmailError('Enter the email you used to request the code.')
      return
    }
    setResending(true)
    try {
      await forgotPassword(currentEmail)
      toast.success('If that email exists, a new code has been sent.')
      setDigits(Array.from({ length: CODE_LENGTH }, () => ''))
      inputRefs.current[0]?.focus()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setResending(false)
    }
  }

  const showEmailEditor = editingEmail || !currentEmail

  return (
    <AuthShell
      title="Enter your reset code"
      description={
        currentEmail
          ? `We sent a 6-digit code to ${currentEmail}. Enter it below — you'll be asked for a new password next.`
          : 'Enter the email the code was sent to, then type the 6 digits from your inbox.'
      }
    >
      <div className="space-y-4">
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        {showEmailEditor ? (
          <Field label="Email" htmlFor="otp-email" required error={emailError}>
            <div className="flex gap-2">
              <Input
                id="otp-email"
                type="email"
                autoComplete="username"
                placeholder="you@clinic.local"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                invalid={Boolean(emailError)}
              />
              {editingEmail ? (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => {
                    setEditingEmail(false)
                    setEmailError('')
                  }}
                >
                  Use this
                </Button>
              ) : null}
            </div>
          </Field>
        ) : (
          <p className="flex items-center justify-between gap-2 rounded-lg bg-brand-faint px-3 py-2 text-sm text-ink">
            <span>
              Code sent to <span className="font-semibold">{currentEmail}</span>
            </span>
            <button
              type="button"
              className="shrink-0 font-medium text-brand hover:text-brand-dark"
              onClick={() => {
                setEditingEmail(true)
                setEmailError('')
              }}
            >
              Change email
            </button>
          </p>
        )}

        <div>
          <div className="flex justify-between gap-2 sm:gap-3">
            {digits.map((digit, index) => (
              <Input
                key={index}
                ref={setRef(index)}
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={1}
                aria-label={`Digit ${index + 1}`}
                className="h-12 w-full px-3 text-center text-lg font-semibold sm:w-12"
                value={digit}
                onChange={(e) => handleChange(index, e.target.value)}
                onKeyDown={(e) => handleKeyDown(index, e)}
                onPaste={(e) => handlePaste(index, e)}
                onFocus={(e) => e.target.select()}
              />
            ))}
          </div>
          <p className="mt-2 text-xs text-ink-muted">
            Type or paste your 6-digit code — it checks automatically.
          </p>
        </div>

        <Button className="w-full" size="lg" loading={loading} onClick={() => submit(digits.join(''))} disabled={loading}>
          Verify code
        </Button>

        <p className="text-center text-sm text-ink-muted">
          Didn&apos;t get a code?{' '}
          <button
            type="button"
            className="font-medium text-brand hover:text-brand-dark disabled:opacity-60"
            onClick={handleResend}
            disabled={resending}
          >
            {resending ? 'Sending…' : 'Send a new code'}
          </button>
        </p>
        <p className="text-center text-sm text-ink-muted">
          <Link className="font-medium text-brand hover:text-brand-dark" to="/login">
            Back to sign in
          </Link>
        </p>
      </div>
    </AuthShell>
  )
}