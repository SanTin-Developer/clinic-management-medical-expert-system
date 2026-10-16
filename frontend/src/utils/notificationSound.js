const SOUND_SRC = '/audio/mixkit-correct-answer-tone-2870.wav'
const MIN_INTERVAL_MS = 900

let audio = null
let primed = false
let lastPlayedAt = 0

function getAudio() {
  if (typeof window === 'undefined') return null
  if (!audio) {
    audio = new Audio(SOUND_SRC)
    audio.preload = 'auto'
    audio.volume = 0.7
  }
  return audio
}

/**
 * Unlock audio playback on the first user gesture so later programmatic plays
 * (realtime alerts) are not blocked by the browser autoplay policy.
 */
export function primeNotificationAudio() {
  if (primed) return
  const el = getAudio()
  if (!el) return

  const restore = () => {
    el.pause()
    el.currentTime = 0
    el.volume = 0.7
    primed = true
  }

  el.volume = 0
  const result = el.play()
  if (result && typeof result.then === 'function') {
    result.then(restore).catch(() => {
      el.volume = 0.7
    })
  } else {
    restore()
  }
}

/**
 * Play the notification tone. Throttled so a burst of alerts only chirps once.
 */
export function playNotificationSound() {
  const el = getAudio()
  if (!el) return

  const now = Date.now()
  if (now - lastPlayedAt < MIN_INTERVAL_MS) return
  lastPlayedAt = now

  try {
    el.currentTime = 0
    const result = el.play()
    if (result && typeof result.catch === 'function') {
      result.catch(() => {
        // Autoplay blocked until the next user gesture primes playback.
      })
    }
  } catch {
    // Ignore; playback will be retried after the next user gesture.
  }
}
