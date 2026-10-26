import { useMemo } from 'react'
import { useAll } from '../../hooks/usePaginated.js'
import { expertApi } from '../../api/expert.js'
import Field from '../ui/Field.jsx'
import { RowSkeleton } from '../ui/Skeleton.jsx'

export default function SymptomMultiSelect({ value, onChange, excludeIds = [], disabled }) {
  const symptoms = useAll(['symptoms', 'all'], (params) => expertApi.symptoms(params))

  const options = useMemo(() => {
    const active = (symptoms.data ?? []).filter((s) => s.is_active)
    const seen = new Set()
    const unique = []
    for (const s of active) {
      if (seen.has(s.id)) continue
      seen.add(s.id)
      unique.push(s)
    }
    return unique
  }, [symptoms.data])

  if (symptoms.isLoading) {
    return (
      <div className="rounded-xl border border-line p-3">
        <RowSkeleton rows={3} cols={2} />
      </div>
    )
  }

  if (symptoms.isError) {
    return (
      <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
        Could not load symptoms: {symptoms.error.message}
      </p>
    )
  }

  if (options.length === 0) {
    return <p className="text-sm text-ink-muted">No active symptoms in the knowledge base yet.</p>
  }

  return (
    <Field label="Link symptoms" hint="Only active symptoms can be linked.">
      <div className="max-h-56 overflow-y-auto rounded-xl border border-line p-2">
        {options.map((s) => {
          const checked = (value ?? []).includes(s.id)
          const disabledOption = disabled || excludeIds.includes(s.id)
          return (
            <label
              key={s.id}
              role="checkbox"
              aria-checked={checked}
              tabIndex={disabledOption ? -1 : 0}
              className="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-canvas aria-checked:bg-brand-soft"
            >
              <span className="flex min-w-0 items-center gap-2">
                <input
                  type="checkbox"
                  className="size-4 accent-brand"
                  checked={checked}
                  disabled={disabledOption}
                  onChange={(e) => {
                    const current = value ?? []
                    onChange(e.target.checked ? [...current, s.id] : current.filter((id) => id !== s.id))
                  }}
                />
                <span className="min-w-0">
                  <span className="block truncate font-medium text-ink">{s.name_km || s.name}</span>
                  <span className="block truncate text-xs text-ink-muted">
                    {[s.name_km ? s.name : null, s.code].filter(Boolean).join(' · ')}
                  </span>
                </span>
              </span>
              {disabledOption ? <span className="text-xs text-ink-muted">linked</span> : null}
            </label>
          )
        })}
      </div>
    </Field>
  )
}

export function MultiSelectOptions({ value, onChange, excludeIds = [], disabled, options, label, hint }) {
  return (
    <Field label={label} hint={hint}>
      <div className="max-h-56 overflow-y-auto rounded-xl border border-line p-2">
        {options.map((item) => {
          const checked = (value ?? []).includes(item.id)
          const disabledOption = disabled || excludeIds.includes(item.id)
          return (
            <label
              key={item.id}
              role="checkbox"
              aria-checked={checked}
              tabIndex={disabledOption ? -1 : 0}
              className="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-canvas aria-checked:bg-brand-soft"
            >
              <span className="flex min-w-0 items-center gap-2">
                <input
                  type="checkbox"
                  className="size-4 accent-brand"
                  checked={checked}
                  disabled={disabledOption}
                  onChange={(e) => {
                    const current = value ?? []
                    onChange(e.target.checked ? [...current, item.id] : current.filter((id) => id !== item.id))
                  }}
                />
                <span className="min-w-0">
                  <span className="block truncate font-medium text-ink">{item.name_km || item.name}</span>
                  <span className="block truncate text-xs text-ink-muted">
                    {[item.name_km ? item.name : null, item.code].filter(Boolean).join(' · ')}
                  </span>
                </span>
              </span>
              {disabledOption ? <span className="text-xs text-ink-muted">linked</span> : null}
            </label>
          )
        })}
      </div>
    </Field>
  )
}