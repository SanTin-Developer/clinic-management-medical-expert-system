import { forwardRef, useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import Input from './Input.jsx'
import { cn } from '../../utils/cn.js'

const SearchInput = forwardRef(function SearchInput(
  { className, onDebounced, debounceMs = 300, ...props },
  ref,
) {
  const [draft, setDraft] = useState('')

  useEffect(() => {
    if (!onDebounced) return undefined
    const id = window.setTimeout(() => onDebounced(draft.trim()), debounceMs)
    return () => window.clearTimeout(id)
  }, [draft, onDebounced, debounceMs])

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