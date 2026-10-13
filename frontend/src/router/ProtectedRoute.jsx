import { Navigate, useLocation } from 'react-router-dom'
import { Spinner } from '../components/ui/Skeleton.jsx'
import { useAuth } from '../hooks/useAuth.js'

export default function ProtectedRoute({ children }) {
  const { isAuthenticated, isLoading, user } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center bg-canvas">
        <Spinner label="Restoring your session..." />
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