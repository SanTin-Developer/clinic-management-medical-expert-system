import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho, isRealtimeConfigured } from '../lib/echo.js'

/**
 * Subscribes to the clinic-wide `dashboard` private channel and refetches the
 * analytics query whenever the backend signals that its cached aggregates are
 * stale. Falls back silently when realtime is not configured.
 */
export function useDashboardRealtime(enabled = true) {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!enabled || !isRealtimeConfigured()) return undefined

    const echo = getEcho()
    if (!echo) return undefined

    const channel = echo.private('dashboard')
    channel.listen('.dashboard.metrics.updated', () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard-analytics'] })
    })

    return () => {
      channel.stopListening('.dashboard.metrics.updated')
      echo.leave('dashboard')
    }
  }, [enabled, queryClient])
}
