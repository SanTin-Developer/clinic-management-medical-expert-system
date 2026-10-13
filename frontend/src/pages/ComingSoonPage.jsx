import { Link } from 'react-router-dom'
import { ArrowLeft, Clock3, Sparkles } from 'lucide-react'

export default function ComingSoonPage({ title, description, Icon }) {
  return (
    <div className="relative grid min-h-[72vh] place-items-center overflow-hidden px-2">
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="animate-float absolute -top-6 left-1/4 size-64 rounded-full bg-brand/20 blur-3xl" />
        <div className="animate-float-slow absolute bottom-0 right-1/4 size-72 rounded-full bg-cyan-400/20 blur-3xl" />
      </div>

      <div className="animate-fade-in-up relative w-full max-w-lg rounded-2xl border border-line/80 bg-surface/85 p-8 text-center shadow-[0_20px_50px_-26px_rgb(37_99_235_/_0.55)] ring-1 ring-brand/5 backdrop-blur-xl">
        <span className="mx-auto grid size-16 place-items-center rounded-2xl bg-gradient-to-br from-brand to-brand-darker text-white shadow-lg shadow-brand/30">
          {Icon ? <Icon className="size-8" aria-hidden /> : <Sparkles className="size-8" aria-hidden />}
        </span>

        <span className="mt-5 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-100 to-orange-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-700 ring-1 ring-amber-200">
          <Clock3 className="size-3.5" aria-hidden />
          Coming Soon
        </span>

        <h1 className="mt-3 text-2xl font-bold tracking-tight text-ink">{title}</h1>
        {description ? <p className="mt-2 text-sm text-ink-muted">{description}</p> : null}

        <Link
          to="/"
          className="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark dark:hover:bg-brand dark:hover:brightness-110"
        >
          <ArrowLeft className="size-4" aria-hidden />
          Back to dashboard
        </Link>
      </div>
    </div>
  )
}
