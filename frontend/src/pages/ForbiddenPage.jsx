import { Link } from 'react-router-dom'
import { ShieldX } from 'lucide-react'
import Button from '../components/ui/Button.jsx'

export default function ForbiddenPage() {
  return (
    <div className="grid min-h-screen place-items-center bg-canvas p-4">
      <div className="flex max-w-md flex-col items-center text-center">
        <span className="grid size-14 place-items-center rounded-2xl bg-amber-100 text-amber-600">
          <ShieldX className="size-7" aria-hidden />
        </span>
        <p className="mt-4 text-sm font-semibold tracking-widest text-ink-muted">403</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-ink">Access denied</h1>
        <p className="mt-2 text-sm text-ink-muted">
          You do not have permission to view this page. Ask an administrator if you think this is a mistake.
        </p>
        <Link to="/" className="mt-6">
          <Button>Back to dashboard</Button>
        </Link>
      </div>
    </div>
  )
}