import { Toaster } from 'sonner'
import { useTheme } from '../../hooks/useTheme.js'

/**
 * Global toast host. The visual treatment (severity rail, icon chip,
 * typography, close control) lives in styles/globals.css under
 * "Toast notifications" — call sites keep using toast.success(),
 * toast.error() and toast.info() unchanged.
 */
export default function ThemedToaster() {
  const { isDark } = useTheme()

  return (
    <Toaster
      position="top-center"
      gap={14}
      visibleToasts={4}
      closeButton
      theme={isDark ? 'dark' : 'light'}
      toastOptions={{ className: 'toast-card' }}
    />
  )
}
