import Modal from './Modal.jsx'
import Badge from './Badge.jsx'

export default function RecordDetailModal({
  open,
  onClose,
  title,
  subtitle,
  badges = [],
  fields = [],
  children,
}) {
  const visibleBadges = badges.filter(Boolean)
  const visibleFields = fields.filter(Boolean)

  return (
    <Modal open={open} onClose={onClose} title={title} description={subtitle} size="md">
      <div className="space-y-4">
        {visibleBadges.length ? (
          <div className="flex flex-wrap items-center gap-2">
            {visibleBadges.map((badge, index) => (
              <Badge key={`${badge.label}-${index}`} tone={badge.tone}>
                {badge.label}
              </Badge>
            ))}
          </div>
        ) : null}

        {children}

        {visibleFields.length ? (
          <dl className="divide-y divide-line">
            {visibleFields.map((field) => (
              <div key={field.label} className="grid grid-cols-3 gap-3 py-2.5">
                <dt className="text-sm font-medium text-ink-muted">{field.label}</dt>
                <dd className="col-span-2 break-words text-sm text-ink">{field.value ?? '—'}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </div>
    </Modal>
  )
}
