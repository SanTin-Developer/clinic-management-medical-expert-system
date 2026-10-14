import { useRef, useState } from 'react'
import { CheckCircle2, FileText, UploadCloud, X } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { formatBytes } from '../../utils/format.js'

export default function FileDropzone({
  id,
  value,
  onSelect,
  accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp',
  hint = 'PDF, DOC, DOCX, JPG, PNG, WEBP · up to 10MB',
  disabled = false,
}) {
  const inputRef = useRef(null)
  const [dragging, setDragging] = useState(false)

  const handleFiles = (files) => {
    const file = files?.[0]
    if (file) onSelect?.(file)
  }

  return (
    <div>
      <input
        ref={inputRef}
        id={id}
        type="file"
        accept={accept}
        disabled={disabled}
        className="sr-only"
        onChange={(event) => {
          handleFiles(event.target.files)
          event.target.value = ''
        }}
      />

      {value ? (
        <div className="flex items-center gap-3 rounded-2xl border border-brand/30 bg-brand-faint/60 p-3 dark:bg-brand-soft/20">
          <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
            <FileText className="size-5" aria-hidden />
          </span>
          <div className="min-w-0 flex-1">
            <p className="flex items-center gap-1.5 truncate text-sm font-semibold text-ink">
              <CheckCircle2 className="size-4 shrink-0 text-emerald-500" aria-hidden />
              <span className="truncate">{value.name}</span>
            </p>
            <p className="mt-0.5 text-xs text-ink-muted">{formatBytes(value.size)} · ready to upload</p>
          </div>
          <button
            type="button"
            onClick={() => onSelect?.(null)}
            disabled={disabled}
            aria-label="Remove file"
            className="grid size-8 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
          >
            <X className="size-4" aria-hidden />
          </button>
        </div>
      ) : (
        <button
          type="button"
          disabled={disabled}
          onClick={() => inputRef.current?.click()}
          onDragOver={(event) => {
            event.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(event) => {
            event.preventDefault()
            setDragging(false)
            handleFiles(event.dataTransfer.files)
          }}
          className={cn(
            'group flex w-full flex-col items-center justify-center gap-2.5 rounded-2xl border-2 border-dashed px-6 py-7 text-center transition-colors disabled:cursor-not-allowed disabled:opacity-60',
            dragging
              ? 'border-brand bg-brand-soft/50 dark:bg-brand-soft/30'
              : 'border-line bg-canvas hover:border-brand/50 hover:bg-brand-soft/30',
          )}
        >
          <span className="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand shadow-sm transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-105">
            <UploadCloud className="size-6" aria-hidden />
          </span>
          <span className="text-sm text-ink">
            <span className="font-semibold text-brand">Click to upload</span>
            <span className="text-ink-muted"> or drag &amp; drop</span>
          </span>
          <span className="text-xs text-ink-muted">{hint}</span>
        </button>
      )}
    </div>
  )
}
