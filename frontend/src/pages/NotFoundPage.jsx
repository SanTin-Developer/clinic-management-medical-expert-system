import { Link } from 'react-router-dom'
import { Compass } from 'lucide-react'
import Button from '../components/ui/Button.jsx'

import { usePageTitle } from '../hooks/usePageTitle.js'

export default function NotFoundPage() {
  usePageTitle('Not Found')
  return (
    <div className="grid min-h-screen place-items-center bg-canvas p-4">
      <div className="flex max-w-md flex-col items-center text-center">
        <span className="grid size-14 place-items-center rounded-2xl bg-brand-soft text-brand">
          <Compass className="size-7" aria-hidden />
        </span>
        <p className="mt-4 text-sm font-semibold tracking-widest text-ink-muted">404</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-ink">Page not found</h1>
        <p className="mt-2 text-sm text-ink-muted">
          The page you're looking for doesn't exist or has been moved.
        </p>
        <Link to="/" className="mt-6">
          <Button>Back to dashboard</Button>
        </Link>
      </div>
    </div>
  )
}