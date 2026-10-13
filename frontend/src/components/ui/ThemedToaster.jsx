import { Toaster } from 'sonner'
import { useTheme } from '../../hooks/useTheme.js'

export default function ThemedToaster() {
  const { isDark } = useTheme()

  return <Toaster position="top-right" richColors closeButton theme={isDark ? 'dark' : 'light'} />
}
