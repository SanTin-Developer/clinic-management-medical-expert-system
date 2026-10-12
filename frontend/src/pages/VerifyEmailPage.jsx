import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CheckCircle2, MailWarning, XCircle } from 'lucide-react'
import { verifyEmailUrl } from '../api/auth.js'
import AuthShell from './auth/AuthShell.jsx'
import { Spinner } from '../components/ui/Skeleton.jsx'

export default function VerifyEmailPage() {
  const [searchParams] = useSearchParams()
  const verifyUrl = searchParams.get('verify_url') ?? ''
  const [status, setStatus] = useState(verifyUrl ? 'verifying' : 'missing')
  const [message, setMessage] = useState(
    verifyUrl ? '' : 'This verification link is missing its token. Open the link from your email again.',
  )

  useEffect(() => {
    if (!verifyUrl) return undefined
    let active = true
    verifyEmailUrl(verifyUrl)
      .then((res) => {
        if (!active) return
        setStatus('success')
        setMessage(res?.message ?? 'Your email address has been verified.')
      })
      .catch((err) => {
        if (!active) return
        setStatus('error')
        setMessage(err.message)
      })
    return () => {
      active = false
    }
  }, [verifyUrl])

  return (
    <AuthShell title="Email verification" description="Confirming your email address.">
      <div className="flex flex-col items-center gap-4 text-center">
        {status === 'verifying' ? (
          <>
            <Spinner label="Verifying your email…" />
            <p className="text-sm text-ink-muted">This only takes a moment.</p>
          </>
        ) : null}

        {status === 'success' ? (
          <>
            <span className="grid size-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
              <CheckCircle2 className="size-7" aria-hidden />
            </span>
            <p role="status" className="text-sm text-ink">
              {message}
            </p>
            <p className="text-sm text-ink-muted">You can now return to the application.</p>
          </>
        ) : null}

        {status === 'error' ? (
          <>
            <span className="grid size-14 place-items-center rounded-2xl bg-red-50 text-red-600">
              <XCircle className="size-7" aria-hidden />
            </span>
            <p role="alert" className="text-sm font-medium text-red-700">
              {message}
            </p>
            <p className="text-sm text-ink-muted">
              The link may have expired. Sign in and request a new verification email from Settings.
            </p>
          </>
        ) : null}

        {status === 'missing' ? (
          <>
            <span className="grid size-14 place-items-center rounded-2xl bg-amber-50 text-amber-600">
              <MailWarning className="size-7" aria-hidden />
            </span>
            <p role="alert" className="text-sm font-medium text-amber-800">
              {message}
            </p>
          </>
        ) : null}

        <Link
          to="/login"
          className="inline-flex h-10 w-full items-center justify-center rounded-lg border border-line bg-surface text-sm font-semibold text-ink transition-colors hover:bg-canvas"
        >
          Back to sign in
        </Link>
      </div>
    </AuthShell>
  )
}