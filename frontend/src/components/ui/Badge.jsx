import { cn } from '../../utils/cn.js'

const TONES = {
  gray: 'bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200',
  green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  amber: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
  brand: 'bg-brand-soft text-brand-dark',
  navy: 'bg-blue-900/10 text-ink dark:bg-blue-400/15',
}

export default function Badge({ tone = 'gray', children, className }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium',
        TONES[tone],
        className,
      )}
    >
      {children}
    </span>
  )
}