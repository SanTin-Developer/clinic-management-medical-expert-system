import { Navigate, useLocation } from 'react-router-dom'
import { LoadingBar, LoadingPill, Shimmer } from '../components/ui/Skeleton.jsx'
import { useAuth } from '../hooks/useAuth.js'

export default function ProtectedRoute({ children }) {
  const { isAuthenticated, isLoading, user } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center bg-canvas px-6">
        <div
          role="status"
          aria-live="polite"
          aria-label="Restoring your session"
          className="w-full max-w-sm space-y-5 rounded-2xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]"
        >
          <div className="flex justify-center">
            <LoadingPill label="Restoring your session" />
          </div>
          <LoadingBar />
          <div className="space-y-3">
            <Shimmer className="mx-auto h-4 w-3/4" />
            <Shimmer className="mx-auto h-3 w-1/2" />
          </div>
        </div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  if (user?.must_change_password && location.pathname !== '/settings') {
    return <Navigate to="/settings" replace />
  }

  return children
}