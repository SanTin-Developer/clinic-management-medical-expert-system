import Modal from './Modal.jsx'
import Button from './Button.jsx'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  children,
  confirmLabel,
  danger = true,
  loading = false,
  confirmDisabled = false,
}) {
  const { t } = useLanguage()

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title ?? t('Are you sure?')}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            {t('Cancel')}
          </Button>
          <Button
            variant={danger ? 'danger' : 'primary'}
            onClick={onConfirm}
            loading={loading}
            disabled={confirmDisabled}
          >
            {confirmLabel ?? t('Confirm')}
          </Button>
        </>
      }
    >
      {message ? <p className="text-sm text-ink-muted">{message}</p> : null}
      {children}
    </Modal>
  )
}