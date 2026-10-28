import { Toaster } from 'sonner'
import { useTheme } from '../../hooks/useTheme.js'

export default function ThemedToaster() {
  const { isDark } = useTheme()

  return (
    <Toaster
      position="top-center"
      richColors
      closeButton
      theme={isDark ? 'dark' : 'light'}
      toastOptions={{
        className: '!rounded-xl !border !border-line/80 !bg-surface !text-ink !shadow-xl !backdrop-blur-sm',
        style: {
          borderRadius: '0.75rem',
          gap: '0.5rem',
          padding: '0.875rem 1rem',
        },
      }}
    />
  )
}
