import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, Banknote, CalendarX2, Pencil, Pill } from 'lucide-react'
import { appointmentApi } from '../api/appointments.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import AppointmentFormModal from '../components/appointments/AppointmentFormModal.jsx'
import { ErrorState } from '../components/ui/State.jsx'
import { formatDate, formatTime, patientFullName } from '../utils/format.js'
import { statusBadgeFor, APPOINTMENT_STATUS } from '../utils/status.js'

export default function AppointmentDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const navigate = useNavigate()

  const query = useQuery({
    queryKey: ['appointment', id],
    queryFn: () => appointmentApi.get(id),
  })

  const [editOpen, setEditOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [busy, setBusy] = useState(false)

  const apt = query.data
  const status = apt ? statusBadgeFor(APPOINTMENT_STATUS, apt.status) : null
  const cancellable =
    apt && !['cancelled', 'completed', 'no_show'].includes(apt.status)

  const confirmCancel = async () => {
    setBusy(true)
    try {
      await appointmentApi.cancel(id)
      toast.success('Appointment cancelled.')
      setCancelOpen(false)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <PageHeader
        title={apt ? apt.appointment_number : 'Appointment'}
        description={apt && apt.patient ? `For ${patientFullName(apt.patient)}` : undefined}
        breadcrumb={[{ label: 'Appointments', to: '/appointments' }, { label: 'Details' }]}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/appointments')}>
              <ArrowLeft className="size-4" aria-hidden /> Back
            </Button>
            {can('appointments.update') && cancellable ? (
              <Button onClick={() => setEditOpen(true)}>
                <Pencil className="size-4" aria-hidden /> Reschedule
              </Button>
            ) : null}
            {can('appointments.update') && cancellable ? (
              <Button variant="danger" onClick={() => setCancelOpen(true)}>
                <CalendarX2 className="size-4" aria-hidden /> Cancel
              </Button>
            ) : null}
          </>
        }
      />

      {query.isLoading ? (
        <CardSkeleton rows={5} />
      ) : query.isError ? (
        <ErrorState
          title="Could not load appointment"
          message={query.error.message}
          onRetry={() => query.refetch()}
        />
      ) : (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <Card title="Appointment details">
              <dl className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                {[
                  ['Appointment number', apt.appointment_number],
                  ['Status', null],
                  ['Patient', apt.patient ? patientFullName(apt.patient) : '—'],
                  ['Doctor', apt.doctor?.name ?? '—'],
                  ['Date', formatDate(apt.appointment_date)],
                  ['Time', formatTime(apt.appointment_time)],
                  ['Reason', apt.reason || '—'],
                  ['Note', apt.note || '—'],
                ].map(([label, value]) => (
                  <div key={label}>
                    <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">{label}</dt>
                    <dd className="mt-1 text-sm font-medium text-ink">
                      {label === 'Status' && status ? (
                        <Badge tone={status.tone}>{status.label}</Badge>
                      ) : (
                        value
                      )}
                    </dd>
                  </div>
                ))}
              </dl>
            </Card>
          </div>
          <div className="space-y-4">
            <Card title="Patient actions">
              <div className="grid gap-2">
                {can('patients.view') && apt?.patient ? (
                  <Button
                    variant="outline"
                    className="justify-start"
                    onClick={() => navigate(`/patients/${apt.patient.id}`)}
                  >
                    View patient profile
                  </Button>
                ) : null}
                {can('prescriptions.create') && apt?.patient ? (
                  <a
                    href={`/prescriptions`}
                    onClick={(e) => {
                      e.preventDefault()
                      navigate('/prescriptions')
                    }}
                    className="inline-flex items-center justify-center gap-2 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                  >
                    <Pill className="size-4" aria-hidden /> Prescribe
                  </a>
                ) : null}
                {can('billing.create') && apt?.patient ? (
                  <Button variant="outline" className="justify-start" onClick={() => navigate(`/billing`)}>
                    <Banknote className="size-4" aria-hidden /> Create invoice
                  </Button>
                ) : null}
              </div>
            </Card>
          </div>
        </div>
      )}

      <AppointmentFormModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        initial={apt}
        onSaved={() => query.refetch()}
      />

      <ConfirmDialog
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={confirmCancel}
        title="Cancel appointment?"
        message="The appointment will be marked as cancelled and its time slot freed."
        confirmLabel="Cancel appointment"
        loading={busy}
      />
    </div>
  )
}