import { forwardRef, useEffect, useRef, useState } from 'react'
import { Search } from 'lucide-react'
import Input from './Input.jsx'
import { cn } from '../../utils/cn.js'

const SearchInput = forwardRef(function SearchInput(
  { className, onDebounced, debounceMs = 300, ...props },
  ref,
) {
  const [draft, setDraft] = useState('')
  const onDebouncedRef = useRef(onDebounced)

  // Track the latest callback so the debounce timer below only re-arms when
  // the draft actually changes. Depending on the inline handler's identity
  // would re-schedule the timer on every parent re-render (e.g. when a new
  // page of data arrives), firing stale calls such as a page reset to 1.
  useEffect(() => {
    onDebouncedRef.current = onDebounced
  })

  useEffect(() => {
    const id = window.setTimeout(() => onDebouncedRef.current?.(draft.trim()), debounceMs)
    return () => window.clearTimeout(id)
  }, [draft, debounceMs])

  return (
    <div className="relative">
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted"
        aria-hidden
      />
      <Input
        ref={ref}
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        className={cn('pl-9', className)}
        aria-label="Search"
        {...props}
      />
    </div>
  )
})

export default SearchInput