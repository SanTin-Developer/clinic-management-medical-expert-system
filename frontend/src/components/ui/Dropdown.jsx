import { useEffect, useRef, useState } from 'react'
import { cloneElement } from 'react'
import { cn } from '../../utils/cn.js'

export default function Dropdown({ trigger, children, align = 'right', width = 'min-w-48' }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    const onDown = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const triggerProps = {
    onClick: () => setOpen((o) => !o),
    'aria-haspopup': 'menu',
    'aria-expanded': open,
  }

  return (
    <div ref={ref} className="relative">
      {cloneElement(trigger, triggerProps)}
      {open ? (
        <div
          role="menu"
          className={cn(
            'animate-fade-in absolute z-30 mt-2 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-[var(--shadow-dialog)]',
            align === 'right' ? 'right-0' : 'left-0',
            width,
          )}
        >
          {children}
        </div>
      ) : null}
    </div>
  )
}

export function DropdownItem({ children, onClick, danger, disabled }) {
  return (
    <button
      type="button"
      role="menuitem"
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition-colors hover:bg-canvas disabled:opacity-50',
        danger ? 'text-red-600' : 'text-ink',
      )}
    >
      {children}
    </button>
  )
}