import { useEffect, useRef, useState } from 'react'
import { Download, X } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/** After a dismissal the alert stays away for two weeks. */
const SNOOZE_MS = 14 * 24 * 60 * 60 * 1000
const STORAGE_KEY = 'royalcare-install-prompt-dismissed'

/** True when the site is already running as an installed app. */
function isStandalone() {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches === true ||
    window.navigator.standalone === true
  )
}

/** iOS Safari (no beforeinstallprompt) needs manual Add-to-Home-Screen steps. */
function isIOS() {
  return (
    /iP(hone|ad|od)/.test(window.navigator.userAgent) ||
    (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1)
  )
}

function isDismissed() {
  try {
    const at = Number(window.localStorage.getItem(STORAGE_KEY))
    return Number.isFinite(at) && at > 0 && Date.now() - at < SNOOZE_MS
  } catch {
    return false
  }
}

function rememberDismissed() {
  try {
    window.localStorage.setItem(STORAGE_KEY, String(Date.now()))
  } catch {
    /* private browsing — carry on without remembering */
  }
}

/**
 * The "Install RoyalCare Clinic" alert shown shortly after a visit. It uses
 * the native beforeinstallprompt where the browser supports it (Chrome/Edge,
 * Android) and falls back to Add-to-Home-Screen steps on iOS Safari. It never
 * shows when the app is already installed, and not again for two weeks after
 * a dismissal.
 */
export default function InstallPrompt() {
  const eventRef = useRef(null)
  const [open, setOpen] = useState(false)
  // True while a native beforeinstallprompt event is available to replay.
  const [hasPrompt, setHasPrompt] = useState(false)

  useEffect(() => {
    if (isStandalone() || isDismissed()) return undefined

    let timerFired = false

    // Chromium (Chrome/Edge/Opera) can install from the browser menu even when
    // beforeinstallprompt never fires, so offer the manual steps there too.
    const isChromium = () => /Chrome\/|Chromium\/|Edg\//.test(window.navigator.userAgent)

    const reveal = () => {
      if (isStandalone() || isDismissed()) return
      if (eventRef.current || isIOS() || isChromium()) setOpen(true)
    }

    const onBeforeInstall = (event) => {
      // Suppress the browser's own mini-infobar — the site renders its alert.
      event.preventDefault()
      eventRef.current = event
      setHasPrompt(true)
      if (timerFired) reveal()
    }

    const onInstalled = () => {
      eventRef.current = null
      setHasPrompt(false)
      setOpen(false)
      rememberDismissed()
    }

    const timer = window.setTimeout(() => {
      timerFired = true
      reveal()
    }, 2600)

    window.addEventListener('beforeinstallprompt', onBeforeInstall)
    window.addEventListener('appinstalled', onInstalled)
    return () => {
      window.clearTimeout(timer)
      window.removeEventListener('beforeinstallprompt', onBeforeInstall)
      window.removeEventListener('appinstalled', onInstalled)
    }
  }, [])

  if (!open) return null

  const canPrompt = hasPrompt
  const ios = isIOS()

  let copy = 'Get one-tap access from your home screen, faster loading and instant clinic updates.'
  if (!canPrompt && ios) {
    copy = 'Open this site like an app: tap Share, then choose “Add to Home Screen”.'
  } else if (!canPrompt) {
    copy = 'Open your browser menu (⋮) and choose “Install RoyalCare Clinic”.'
  }

  const dismiss = () => {
    setOpen(false)
    rememberDismissed()
  }

  const install = async () => {
    const event = eventRef.current
    dismiss() // accepted or not, don't nag again soon
    if (!event) return
    eventRef.current = null
    setHasPrompt(false)
    try {
      await event.prompt()
      await event.userChoice
    } catch {
      /* a prompt can only be shown once — ignore failures */
    }
  }

  return (
    <div
      role="dialog"
      aria-label="Install RoyalCare Clinic"
      className={cn(
        'animate-sheet-in fixed inset-x-3 z-[45] overflow-hidden rounded-3xl border border-white/50 bg-white/95 shadow-[var(--shadow-float)] backdrop-blur-xl',
        'bottom-[calc(4.75rem_+_env(safe-area-inset-bottom))]',
        'md:inset-x-auto md:right-6 md:bottom-6 md:w-[380px]',
      )}
    >
      <button
        type="button"
        onClick={dismiss}
        aria-label="Close install prompt"
        className="absolute right-3 top-3 grid size-8 place-items-center rounded-full text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
      >
        <X className="size-4" aria-hidden />
      </button>

      <div className="flex items-start gap-3.5 p-5 pr-12">
        <img
          src="/royalcare-logo.png"
          alt=""
          aria-hidden
          className="size-12 shrink-0 rounded-2xl bg-white object-contain p-1 shadow-sm ring-1 ring-line"
        />
        <div className="min-w-0">
          <p className="text-sm font-extrabold tracking-tight text-ink">Install RoyalCare Clinic</p>
          <p className="mt-1 text-[13px] leading-relaxed text-ink-muted">{copy}</p>
        </div>
      </div>

      <div className="flex gap-2 px-5 pb-5">
        {canPrompt ? (
          <>
            <button
              type="button"
              onClick={install}
              className="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink shadow-[0_12px_26px_-12px_rgb(76_175_80_/_0.9)] transition hover:bg-accent-dark"
            >
              <Download className="size-4" aria-hidden />
              Install now
            </button>
            <button
              type="button"
              onClick={dismiss}
              className="rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink-muted transition-colors hover:bg-canvas"
            >
              Maybe later
            </button>
          </>
        ) : (
          <button
            type="button"
            onClick={dismiss}
            className="inline-flex flex-1 items-center justify-center rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink shadow-[0_12px_26px_-12px_rgb(76_175_80_/_0.9)] transition hover:bg-accent-dark"
          >
            Got it
          </button>
        )}
      </div>
    </div>
  )
}
