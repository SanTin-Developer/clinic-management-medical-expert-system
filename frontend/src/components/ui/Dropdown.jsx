import { useEffect, useRef, useState } from 'react'
import { cloneElement } from 'react'
import { cn } from '../../utils/cn.js'

export default function Dropdown({
  trigger,
  children,
  align = 'right',
  width = 'min-w-48',
  className,
  isOpen,
  onOpenChange,
  panelRole = 'menu',
  hasPopup = 'menu',
  panelLabel,
  scrollable = false,
}) {
  const [internalOpen, setInternalOpen] = useState(false)
  const isControlled = isOpen !== undefined
  const open = isControlled ? isOpen : internalOpen
  const ref = useRef(null)
  const onOpenChangeRef = useRef(onOpenChange)

  useEffect(() => {
    onOpenChangeRef.current = onOpenChange
  }, [onOpenChange])

  useEffect(() => {
    if (!open) return undefined
    const onDown = (e) => {
      if (ref.current && !ref.current.contains(e.target)) {
        if (!isControlled) setInternalOpen(false)
        onOpenChangeRef.current?.(false)
      }
    }
    const onKey = (e) => {
      if (e.key === 'Escape') {
        if (!isControlled) setInternalOpen(false)
        onOpenChangeRef.current?.(false)
      }
    }
    document.addEventListener('mousedown', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open, isControlled])

  const setOpen = (next) => {
    if (next === open) return
    if (!isControlled) setInternalOpen(next)
    onOpenChangeRef.current?.(next)
  }

  const triggerProps = {
    onClick: () => setOpen(!open),
    'aria-haspopup': hasPopup,
    'aria-expanded': open,
    'data-open': open,
  }

  return (
    <div ref={ref} className="relative">
      {cloneElement(trigger, triggerProps)}
      {open ? (
        <div
          role={panelRole}
          aria-label={panelLabel}
          className={cn(
            'animate-fade-in absolute z-30 mt-2 rounded-xl border border-line bg-surface py-1 shadow-[var(--shadow-dialog)]',
            scrollable
              ? 'max-h-[calc(100vh-6rem)] overflow-y-auto overscroll-contain'
              : 'overflow-hidden',
            align === 'right' ? 'right-0' : 'left-0',
            width,
            className,
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
