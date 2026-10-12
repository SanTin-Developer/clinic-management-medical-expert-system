import { HeartPulse } from 'lucide-react'

export default function AuthShell({ children, title, description }) {
  return (
    <div className="grid min-h-screen place-items-center bg-canvas p-4">
      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <span className="grid size-14 place-items-center rounded-2xl bg-brand text-white shadow-[var(--shadow-dialog)]">
            <HeartPulse className="size-7" aria-hidden />
          </span>
          <h1 className="mt-4 text-2xl font-bold tracking-tight text-ink">{title}</h1>
          {description ? (
            <p className="mt-1 text-sm text-ink-muted">{description}</p>
          ) : null}
        </div>
        <div className="rounded-2xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
          {children}
        </div>
      </div>
    </div>
  )
}