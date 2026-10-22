import { forwardRef, useEffect, useRef } from 'react'
import { Check } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/**
 * App checkbox — a sleek, minimalist circular control.
 *
 * Design intent: unchecked is a faint, delicate gray ring with a pure white
 * center; checked is a solid deep purple disc with a crisp white tick. A short
 * transition keeps the change feeling polished without being decorative.
 *
 * The colours are fixed (not themed by the brand palette) because the control
 * is intentionally specified as a purple accent that reads cleanly on any
 * surface in both light and dark themes.
 *
 * The real <input type="checkbox"> stays in the DOM — visually hidden but
 * focusable — so keyboard interaction, labels, forms and assistive tech keep
 * working exactly as before. `indeterminate` covers the "some but not all"
 * state used by "select all" controls.
 */
const CHECKED_BG = 'peer-checked:bg-[#6d28d9]'
const INDETERMINATE_BG = 'peer-indeterminate:bg-[#6d28d9]'

const Checkbox = forwardRef(function Checkbox(
  { className, indeterminate = false, disabled, ...props },
  ref,
) {
  const inputRef = useRef(null)

  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.indeterminate = indeterminate
    }
  }, [indeterminate])

  const setRefs = (element) => {
    inputRef.current = element
    if (typeof ref === 'function') {
      ref(element)
    } else if (ref) {
      ref.current = element
    }
  }

  return (
    <span
      className={cn(
        'group/checkbox relative inline-flex size-[20px] shrink-0 items-center justify-center',
        disabled && 'cursor-not-allowed opacity-50',
        className,
      )}
    >
      <input
        ref={setRefs}
        type="checkbox"
        disabled={disabled}
        className="peer absolute inset-0 z-10 m-0 cursor-pointer opacity-0 disabled:cursor-not-allowed"
        {...props}
      />
      <span
        aria-hidden
        className={cn(
          // Unchecked: a faint, delicate gray ring with a pure white center.
          'grid size-full place-items-center rounded-full border border-slate-300 bg-white',
          'dark:border-white/20 dark:bg-transparent',
          'transition-colors duration-150 ease-out',
          // Interaction feedback.
          'peer-hover:border-[#6d28d9]',
          'peer-focus-visible:ring-2 peer-focus-visible:ring-[#6d28d9]/40 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface',
          // Checked / indeterminate: solid deep purple disc, no second outline.
          'peer-checked:border-[#6d28d9] peer-indeterminate:border-[#6d28d9]',
          CHECKED_BG,
          INDETERMINATE_BG,
          'peer-disabled:cursor-not-allowed',
        )}
      >
        <Check
          strokeWidth={3.5}
          strokeLinecap="round"
          strokeLinejoin="round"
          className={cn(
            'size-3 text-white transition-opacity duration-150 ease-out',
            'opacity-0',
            'peer-checked:opacity-100 peer-indeterminate:hidden',
          )}
        />
        <span
          aria-hidden
          className={cn(
            'absolute h-[2px] w-[9px] rounded-full bg-white transition-opacity duration-150 ease-out',
            'opacity-0',
            'peer-indeterminate:opacity-100',
          )}
        />
      </span>
    </span>
  )
})

export default Checkbox

/**
 * Whole-card selectable wrapper for grid galleries: an accessible checkbox
 * button fills the card so clicking anywhere toggles selection, while staying
 * focusable and screen-reader friendly.
 *
 * Pass `onOpen` to split the two intents: the checkbox area keeps toggling
 * selection, and everything else (the media itself) opens the item instead —
 * used by the media library so a click previews the image.
 */
export function SelectableCard({ selected, onToggle, onOpen, label, className, children }) {
  const openMode = typeof onOpen === 'function'

  const activate = () => {
    if (openMode) {
      onOpen()
      return
    }
    onToggle()
  }

  return (
    <div
      role={openMode ? 'button' : 'checkbox'}
      aria-checked={openMode ? undefined : selected}
      aria-label={label}
      tabIndex={0}
      onClick={activate}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          activate()
        }
      }}
      className={cn(
        'group relative cursor-pointer overflow-hidden rounded-2xl border bg-surface transition-all duration-200 focus-visible:ring-2 focus-visible:ring-[#6d28d9]/40 focus-visible:outline-none',
        selected
          ? 'border-[#6d28d9]/40 shadow-[0_14px_36px_-16px_rgba(109,40,217,0.45)]'
          : 'border-line hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-[var(--shadow-card-hover)]',
        className,
      )}
    >
      {children}
    </div>
  )
}
