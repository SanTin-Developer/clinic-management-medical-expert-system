import { useEffect } from 'react'

/**
 * Locks scrolling behind an overlay (dialog, sheet, lightbox).
 *
 * The admin shell scrolls a `<div>` inside `AppLayout`, not `<body>`, so
 * setting `document.body.style.overflow = 'hidden'` alone leaves the page
 * behind a dialog still scrollable. Elements marked with `data-scroll-lock`
 * (the layout's scroll pane) are locked in addition to the document itself.
 *
 * Locks are reference counted so nested overlays (for example a confirmation
 * dialog opened from another modal) do not release the lock early, and the
 * previous inline styles — including the scrollbar gutter compensation — are
 * restored on the last unlock.
 */
const LOCK_SELECTOR = '[data-scroll-lock]'

let lockCount = 0
let saved = []

function lock() {
  if (lockCount++ > 0) return

  const targets = Array.from(document.querySelectorAll(LOCK_SELECTOR))
  if (document.scrollingElement && !targets.includes(document.scrollingElement)) {
    targets.push(document.scrollingElement)
  }

  saved = targets.map((el) => {
    const gutter = el.offsetWidth - el.clientWidth
    const previous = {
      el,
      overflow: el.style.overflow,
      overscrollBehavior: el.style.overscrollBehavior,
      paddingRight: el.style.paddingRight,
    }

    // Compensate for the scrollbar that disappears while locked so the page
    // behind the overlay does not shift sideways on desktop.
    if (gutter > 0) el.style.paddingRight = `${gutter}px`
    el.style.overflow = 'hidden'
    el.style.overscrollBehavior = 'none'

    return previous
  })
}

function unlock() {
  if (lockCount === 0) return
  if (--lockCount > 0) return

  for (const { el, overflow, overscrollBehavior, paddingRight } of saved) {
    el.style.overflow = overflow
    el.style.overscrollBehavior = overscrollBehavior
    el.style.paddingRight = paddingRight
  }
  saved = []
}

/** Prevents the page behind an overlay from scrolling while `active` is true. */
export function useScrollLock(active) {
  useEffect(() => {
    if (!active) return undefined

    lock()
    return unlock
  }, [active])
}
