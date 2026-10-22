import { createContext } from 'react'

/**
 * Context backing overlay awareness for floating page furniture (such as the
 * back-to-top button) so it can step aside while a dialog or sheet is open.
 *
 * Shape: `{ count, register }` where `count` is the number of open overlays and
 * `register()` returns a release callback that decrements the count.
 */
export const OverlayContext = createContext({ count: 0, register: () => () => {} })
