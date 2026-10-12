import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, CheckCircle2, Trash2, XCircle } from 'lucide-react'
import { prescriptionApi } from '../api/prescriptions.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import { ErrorState } from '../components/ui/State.jsx'
import { formatDateTime, patientFullName } from '../utils/format.js'
import { statusBadgeFor, PRESCRIPTION_STATUS } from '../utils/status.js'

export default function PrescriptionDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const navigate = useNavigate()

  const query = useQuery({
    queryKey: ['prescription', id],
    queryFn: () => prescriptionApi.get(id),
  })

  const [action, setAction] = useState(null)
  const [busy, setBusy] = useState(false)

  const rx = query.data
  const statusMeta = rx ? statusBadgeFor(PRESCRIPTION_STATUS, rx.status) : null
  const pending = rx?.status === 'pending'

  const runAction = async () => {
    if (!action) return
    setBusy(true)
    try {
      const result =
        action === 'dispense'
          ? await prescriptionApi.dispense(id)
          : action === 'cancel'
            ? await prescriptionApi.cancel(id)
            : await prescriptionApi.remove(id)
      if (!result) toast.success(action === 'dispense' ? 'Prescription dispensed.' : 'Prescription cancelled.')
      else toast.success('Prescription updated.')
      setAction(null)
      if (action === 'delete') navigate('/prescriptions')
      else query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <PageHeader
        title={rx ? rx.prescription_number : 'Prescription'}
        description={rx && rx.patient ? `For ${patientFullName(rx.patient)}` : undefined}
        breadcrumb={[{ label: 'Prescriptions', to: '/prescriptions' }, { label: 'Details' }]}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/prescriptions')}>
              <ArrowLeft className="size-4" aria-hidden /> Back
            </Button>
            {can('prescriptions.update') && pending ? (
              <Button variant="success" onClick={() => setAction('dispense')}>
                <CheckCircle2 className="size-4" aria-hidden /> Dispense
              </Button>
            ) : null}
            {can('prescriptions.update') && pending ? (
              <Button variant="danger" onClick={() => setAction('cancel')}>
                <XCircle className="size-4" aria-hidden /> Cancel
              </Button>
            ) : null}
            {can('prescriptions.delete') ? (
              <Button variant="outline" onClick={() => setAction('delete')}>
                <Trash2 className="size-4" aria-hidden /> Delete
              </Button>
            ) : null}
          </>
        }
      />

      {query.isLoading ? (
        <CardSkeleton rows={5} />
      ) : query.isError ? (
        <ErrorState title="Could not load prescription" message={query.error.message} onRetry={() => query.refetch()} />
      ) : (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <Card title="Medicine items">
              <ul className="divide-y divide-line">
                {rx.items?.map((item) => (
                  <li key={item.id} className="flex flex-wrap items-center gap-3 py-3">
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-semibold text-ink">{item.medicine?.name ?? `Medicine #${item.medicine_id}`}</p>
                      <p className="text-xs text-ink-muted">
                        {item.medicine?.code ?? ''}
                        {item.dosage ? ` · ${item.dosage}` : ''}
                        {item.frequency ? ` · ${item.frequency}` : ''}
                        {item.duration ? ` · ${item.duration}` : ''}
                        {item.instructions ? ` · ${item.instructions}` : ''}
                      </p>
                    </div>
                    <Badge tone="pink">Qty {item.quantity}</Badge>
                  </li>
                )) ?? <p className="py-4 text-sm text-ink-muted">No items.</p>}
              </ul>
            </Card>
            {rx.notes ? (
              <Card title="Notes" className="mt-6">
                <p className="text-sm text-ink">{rx.notes}</p>
              </Card>
            ) : null}
          </div>
          <div>
            <Card title="Summary">
              <dl className="space-y-3 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-ink-muted">Status</dt>
                  <dd>{statusMeta ? <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge> : '—'}</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-ink-muted">Patient</dt>
                  <dd className="font-medium text-ink">
                    <button className="hover:text-brand-dark" onClick={() => navigate(`/patients/${rx.patient?.id}`)}>
                      {patientFullName(rx.patient)}
                    </button>
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-ink-muted">Doctor</dt>
                  <dd className="font-medium text-ink">{rx.doctor?.name ?? '—'}</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-ink-muted">Issued</dt>
                  <dd className="text-ink">{formatDateTime(rx.issued_at)}</dd>
                </div>
                {rx.dispensed_at ? (
                  <div className="flex items-center justify-between gap-3">
                    <dt className="text-ink-muted">Dispensed</dt>
                    <dd className="text-ink">{formatDateTime(rx.dispensed_at)}</dd>
                  </div>
                ) : null}
                {rx.dispensed_by ? (
                  <div className="flex items-center justify-between gap-3">
                    <dt className="text-ink-muted">Dispensed by</dt>
                    <dd className="text-ink">
                      {typeof rx.dispensed_by === 'object'
                        ? rx.dispensed_by.name
                        : `User #${rx.dispensed_by}`}
                    </dd>
                  </div>
                ) : null}
                {rx.consultation ? (
                  <div className="flex items-center justify-between gap-3">
                    <dt className="text-ink-muted">Consultation</dt>
                    <dd className="text-ink">#{rx.consultation.id}</dd>
                  </div>
                ) : null}
              </dl>
            </Card>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={Boolean(action)}
        onClose={() => setAction(null)}
        onConfirm={runAction}
        title={
          action === 'dispense'
            ? 'Dispense prescription?'
            : action === 'cancel'
              ? 'Cancel prescription?'
              : 'Delete prescription?'
        }
        message={
          action === 'dispense'
            ? 'Stock will be deducted from inventory batches (FIFO) and the prescription marked dispensed. This is recorded for audit.'
            : action === 'cancel'
              ? 'The prescription will be cancelled and cannot be dispensed afterwards.'
              : 'This permanently deletes the prescription and its items.'
        }
        confirmLabel={
          action === 'dispense' ? 'Dispense' : action === 'cancel' ? 'Cancel prescription' : 'Delete'
        }
        danger={action !== 'dispense'}
        loading={busy}
      />
    </div>
  )
}