import { useState } from 'react'
import { toast } from 'sonner'
import { Download, Trash2 } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import ConfirmDialog from '../ui/ConfirmDialog.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import { exportUserData, deleteUserAccount } from '../../api/userSettings.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function PrivacyDataPanel() {
  const { t } = useLanguage()
  const [exporting, setExporting] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState(null)

  const handleExport = async () => {
    setExporting(true)
    try {
      const res = await exportUserData()
      const dataStr = JSON.stringify(res?.data ?? res, null, 2)
      const blob = new Blob([dataStr], { type: 'application/json' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `clinic-data-export-${Date.now()}.json`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      toast.success(t('Data export downloaded.'))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setExporting(false)
    }
  }

  const handleDelete = async () => {
    setError(null)
    if (!password) {
      setError({ password: t('Enter your password.') })
      return
    }
    if (confirmation.trim().toUpperCase() !== 'DELETE') {
      setError({ confirmation: t('Type DELETE to confirm.') })
      return
    }
    setDeleting(true)
    try {
      await deleteUserAccount({ password, confirmation: confirmation.trim() })
      toast.success(t('Account deleted.'))
      setDeleteOpen(false)
      // Force logout
      setTimeout(() => {
        window.location.href = '/login'
      }, 500)
    } catch (err) {
      toast.error(err.message)
      setError(err.response?.data?.errors || { form: err.message })
    } finally {
      setDeleting(false)
    }
  }

  const closeDelete = () => {
    setDeleteOpen(false)
    setPassword('')
    setConfirmation('')
    setError(null)
  }

  return (
    <>
      <Card title={t('Privacy & Data')} subtitle={t('Download your data or delete your account.')}>
        <div className="space-y-4">
          <div className="flex items-start justify-between gap-4 rounded-xl border border-line p-4">
            <div>
              <p className="text-sm font-semibold text-ink">{t('Download your data')}</p>
              <p className="mt-1 text-sm text-ink-muted">
                {t('Get a copy of your profile and permissions in JSON format.')}
              </p>
            </div>
            <Button variant="outline" onClick={handleExport} loading={exporting}>
              <Download className="size-4" aria-hidden /> {t('Download')}
            </Button>
          </div>
          <div className="flex items-start justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
            <div>
              <p className="text-sm font-semibold text-red-700 dark:text-red-300">{t('Delete account')}</p>
              <p className="mt-1 text-sm text-red-600/80 dark:text-red-300/80">
                {t('This action is permanent and cannot be undone. All your sessions will be revoked.')}
              </p>
            </div>
            <Button variant="danger" onClick={() => setDeleteOpen(true)}>
              <Trash2 className="size-4" aria-hidden /> {t('Delete account')}
            </Button>
          </div>
        </div>
      </Card>

      <ConfirmDialog
        open={deleteOpen}
        onClose={closeDelete}
        onConfirm={handleDelete}
        title={t('Delete your account?')}
        message={t('This will permanently delete your account and revoke all sessions. This action cannot be undone.')}
        confirmLabel={t('Delete account')}
        loading={deleting}
        tone="danger"
      >
        <div className="mt-4 space-y-3">
          <Field label={t('Password')} htmlFor="delete-password" required error={error?.password}>
            <Input
              id="delete-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              invalid={Boolean(error?.password)}
            />
          </Field>
          <Field label={t('Type DELETE to confirm')} htmlFor="delete-confirm" required error={error?.confirmation}>
            <Input
              id="delete-confirm"
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
              placeholder="DELETE"
              invalid={Boolean(error?.confirmation)}
            />
          </Field>
        </div>
      </ConfirmDialog>
    </>
  )
}
