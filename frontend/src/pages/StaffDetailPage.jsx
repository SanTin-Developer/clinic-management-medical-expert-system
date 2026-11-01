import { useState, useEffect, useCallback, useMemo } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import {
  AlertTriangle,
  ArrowLeft,
  Building,
  DollarSign,
  Download,
  Link2,
  Mail,
  MailPlus,
  Pencil,
  Phone,
  Plus,
  Trash2,
  Unlink,
  UserCheck,
  UserX,
} from 'lucide-react'
import { staffApi } from '../api/staff.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import { PageSkeleton } from '../components/ui/Skeleton.jsx'
import { ErrorState, ForbiddenState } from '../components/ui/State.jsx'
import StaffFormModal from '../components/staff/StaffFormModal.jsx'
import StaffSalaryModal from '../components/staff/StaffSalaryModal.jsx'
import StaffLinkUserModal from '../components/staff/StaffLinkUserModal.jsx'
import StaffDocumentModal from '../components/staff/StaffDocumentModal.jsx'
import { formatDate } from '../utils/format.js'
import {
  EMPLOYMENT_STATUS,
  EMPLOYMENT_TYPE,
  STAFF_DOCUMENT_CATEGORIES,
  statusBadgeFor,
} from '../utils/status.js'

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export default function StaffDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can, user } = useAuth()

  const [staff, setStaff] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [formOpen, setFormOpen] = useState(false)
  const [salaryOpen, setSalaryOpen] = useState(false)
  const [linkOpen, setLinkOpen] = useState(false)
  const [docOpen, setDocOpen] = useState(false)

  const [deletingStaff, setDeletingStaff] = useState(false)
  const [unlinkingUser, setUnlinkingUser] = useState(false)
  const [deletingDoc, setDeletingDoc] = useState(null)

  const canViewSalary = can('staff.salary.view')
  const canUpdateSalary = can('staff.salary.update')
  const canViewDocs = can('staff.document.view') || staff?.user_id === user?.id
  const canManageDocs = can('staff.document.upload') || can('staff.update')
  const canDeleteDocs = can('staff.document.delete')

  const fetchStaff = useCallback(() => {
    setLoading(true)
    staffApi.get(id)
      .then((data) => {
        setStaff(data)
        setError(null)
      })
      .catch((err) => {
        setError(err.message || 'Failed to load staff details.')
      })
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    fetchStaff()
  }, [fetchStaff])

  const isLicenseExpired = useMemo(() => {
    if (!staff?.license_expiry) return false
    return new Date(staff.license_expiry).getTime() < Date.now()
  }, [staff?.license_expiry])

  const confirmDeleteStaff = async () => {
    try {
      await staffApi.remove(staff.id)
      toast.success('Staff profile removed.')
      navigate('/staff')
    } catch (err) {
      toast.error(err.message || 'Failed to delete staff profile.')
    }
  }

  const confirmUnlinkUser = async () => {
    try {
      await staffApi.unlinkUser(staff.id)
      toast.success('User account unlinked from this staff profile.')
      setUnlinkingUser(false)
      fetchStaff()
    } catch (err) {
      toast.error(err.message || 'Failed to unlink user account.')
    }
  }

  const confirmDeleteDoc = async () => {
    if (!deletingDoc) return
    try {
      await staffApi.deleteDocument(deletingDoc.id)
      toast.success('Document deleted.')
      setDeletingDoc(null)
      fetchStaff()
    } catch (err) {
      toast.error(err.message || 'Failed to delete document.')
    }
  }

  const handleDownloadDoc = async (doc) => {
    try {
      await staffApi.downloadDocument(doc.id, doc.original_name)
    } catch (err) {
      toast.error(err.message || 'Failed to download document.')
    }
  }

  if (loading) {
    return (
      <PageSkeleton
        title="Staff Profile"
        description="Loading employment details, credentials, documents and compensation…"
        cards={4}
        rows={3}
      />
    )
  }

  if (error || !staff) {
    return (
      <div className="py-12 max-w-lg mx-auto space-y-4">
        {error && error.status === 403 ? (
          <ForbiddenState message="You do not have permission to view this staff profile." />
        ) : (
          <ErrorState
            title="Staff profile not found"
            message={error || 'The requested staff profile does not exist or has been removed.'}
            onRetry={fetchStaff}
          />
        )}
        <div className="text-center">
          <Button variant="outline" onClick={() => navigate('/staff')}>
            Back to Staff Directory
          </Button>
        </div>
      </div>
    )
  }

  const statusMeta = statusBadgeFor(EMPLOYMENT_STATUS, staff.employment_status)
  const typeMeta = statusBadgeFor(EMPLOYMENT_TYPE, staff.employment_type)
  const hasUser = Boolean(staff.user_id && staff.user)

  return (
    <div className="space-y-6">
      <PageHeader
        title={staff.full_name || `${staff.first_name} ${staff.last_name}`}
        description={`${staff.job_title || 'Staff Member'} • ${staff.department || 'General'}`}
        breadcrumb={[
          { label: 'Staff Directory', to: '/staff' },
          { label: staff.full_name || staff.staff_number },
        ]}
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => navigate('/staff')}>
              <ArrowLeft className="size-4" aria-hidden /> Back
            </Button>

            {can('staff.update') ? (
              <Button variant="outline" onClick={() => setFormOpen(true)}>
                <Pencil className="size-4" aria-hidden /> Edit profile
              </Button>
            ) : null}

            {can('staff.delete') ? (
              <Button variant="outline" tone="red" onClick={() => setDeletingStaff(true)}>
                <Trash2 className="size-4" aria-hidden /> Delete
              </Button>
            ) : null}
          </div>
        }
      />

      {/* Overview Header Card */}
      <Card>
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-line">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="font-mono text-xs font-bold text-brand uppercase tracking-wider px-2 py-0.5 rounded bg-brand-50 border border-brand-200">
                {staff.staff_number}
              </span>
              <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
              <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
            </div>
            <h2 className="text-xl font-bold text-ink">
              {staff.full_name}
            </h2>
            <p className="text-sm text-ink-muted">
              {staff.job_title} {staff.department ? `(${staff.department})` : ''}
            </p>
          </div>

          <div className="flex flex-col sm:items-end gap-1 text-xs text-ink-muted">
            <div>
              <span className="font-medium text-ink">Hired:</span> {formatDate(staff.hire_date) || '—'}
            </div>
            {staff.work_schedule ? (
              <div>
                <span className="font-medium text-ink">Schedule:</span> {staff.work_schedule}
              </div>
            ) : null}
          </div>
        </div>

        {/* Quick Contact Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 text-sm">
          <div className="flex items-center gap-2 text-ink">
            <Mail className="size-4 text-ink-muted shrink-0" aria-hidden />
            <span className="truncate">{staff.email || 'No email registered'}</span>
          </div>
          <div className="flex items-center gap-2 text-ink">
            <Phone className="size-4 text-ink-muted shrink-0" aria-hidden />
            <span>{staff.phone || 'No phone registered'}</span>
          </div>
          <div className="flex items-center gap-2 text-ink">
            <Building className="size-4 text-ink-muted shrink-0" aria-hidden />
            <span className="truncate">{staff.department || 'No department'}</span>
          </div>
        </div>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Card 1: Personal & Emergency Contact */}
        <Card title="Personal & Emergency Contact">
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <div>
              <dt className="text-xs font-medium text-ink-muted">Gender</dt>
              <dd className="text-ink capitalize">{staff.gender || '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-ink-muted">Date of birth</dt>
              <dd className="text-ink">{formatDate(staff.date_of_birth) || '—'}</dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-xs font-medium text-ink-muted">Residential address</dt>
              <dd className="text-ink">{staff.address || '—'}</dd>
            </div>
            <div className="sm:col-span-2 pt-2 border-t border-line">
              <dt className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-2">
                Emergency contact
              </dt>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                  <span className="text-xs text-ink-muted">Name:</span>{' '}
                  <span className="text-ink font-medium">{staff.emergency_contact_name || '—'}</span>
                </div>
                <div>
                  <span className="text-xs text-ink-muted">Phone:</span>{' '}
                  <span className="text-ink">{staff.emergency_contact_phone || '—'}</span>
                </div>
              </div>
            </div>
          </dl>
        </Card>

        {/* Card 2: Credentials & Licenses */}
        <Card title="Credentials & Professional Licenses">
          <dl className="space-y-3 text-sm">
            <div className="flex items-start justify-between">
              <div>
                <dt className="text-xs font-medium text-ink-muted">Medical license number</dt>
                <dd className="font-mono text-ink font-semibold">{staff.license_number || '—'}</dd>
              </div>
              {staff.license_expiry ? (
                <div className="text-right">
                  <dt className="text-xs font-medium text-ink-muted">License expiry</dt>
                  <dd>
                    {isLicenseExpired ? (
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-red-600">
                        <AlertTriangle className="size-3.5" aria-hidden /> Expired ({formatDate(staff.license_expiry)})
                      </span>
                    ) : (
                      <span className="text-ink">{formatDate(staff.license_expiry)}</span>
                    )}
                  </dd>
                </div>
              ) : null}
            </div>

            <div>
              <dt className="text-xs font-medium text-ink-muted">Education & Degrees</dt>
              <dd className="text-ink">{staff.education || '—'}</dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-ink-muted">Qualifications & Certifications</dt>
              <dd className="text-ink whitespace-pre-wrap">{staff.qualifications || '—'}</dd>
            </div>

            {staff.supervisor ? (
              <div className="pt-2 border-t border-line">
                <dt className="text-xs font-medium text-ink-muted">Direct supervisor</dt>
                <dd className="text-ink font-medium">
                  <Link to={`/staff/${staff.supervisor.id}`} className="text-brand hover:underline">
                    {staff.supervisor.full_name || staff.supervisor.staff_number}
                  </Link>{' '}
                  <span className="text-xs text-ink-muted">({staff.supervisor.job_title})</span>
                </dd>
              </div>
            ) : null}
          </dl>
        </Card>

        {/* Card 3: System Login Account (Separation of Account vs Employment) */}
        <Card title="System Login Account">
          <div className="text-sm">
            {hasUser ? (
              <div className="space-y-4">
                <div className="rounded-lg border border-line bg-canvas p-4">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <UserCheck className="size-4 text-green-600" aria-hidden />
                      <span className="font-semibold text-ink">@{staff.user.username}</span>
                    </div>
                    <Badge tone={staff.user.account_status === 'active' ? 'green' : 'amber'}>
                      {staff.user.account_status}
                    </Badge>
                  </div>
                  <div className="grid grid-cols-2 gap-2 text-xs text-ink-muted">
                    <div>Email: <span className="text-ink">{staff.user.email}</span></div>
                    <div>Account created: <span className="text-ink">{formatDate(staff.user.created_at)}</span></div>
                  </div>
                </div>

                <div className="flex justify-between items-center pt-2">
                  <p className="text-xs text-ink-muted">
                    Employment status remains separate from account status.
                  </p>
                  {can('staff.update') ? (
                    <Button
                      size="sm"
                      variant="outline"
                      tone="red"
                      onClick={() => setUnlinkingUser(true)}
                    >
                      <Unlink className="size-3.5" aria-hidden /> Unlink login
                    </Button>
                  ) : null}
                </div>
              </div>
            ) : (
              <div className="rounded-lg border border-dashed border-line bg-canvas p-4 text-center">
                <UserX className="size-8 mx-auto text-ink-muted mb-2" aria-hidden />
                <p className="font-medium text-ink">No system login account linked</p>
                <p className="text-xs text-ink-muted mt-1 max-w-md mx-auto">
                  This employee profile exists independently of system accounts. Most clinic staff (cleaners, technicians, etc.) do not need system logins.
                </p>

                {can('staff.update') ? (
                  <div className="flex justify-center gap-2 mt-4">
                    <Button size="sm" onClick={() => setLinkOpen(true)}>
                      <Link2 className="size-3.5" aria-hidden /> Link existing account
                    </Button>
                    {can('users.invite') ? (
                      <Link to="/invitations">
                        <Button size="sm" variant="outline">
                          <MailPlus className="size-3.5" aria-hidden /> Invite new account
                        </Button>
                      </Link>
                    ) : null}
                  </div>
                ) : null}
              </div>
            )}
          </div>
        </Card>

        {/* Card 4: Confidential Documents */}
        {canViewDocs ? <Card
          title="Confidential Documents"
          actions={
            canManageDocs ? (
              <Button size="sm" onClick={() => setDocOpen(true)}>
                <Plus className="size-3.5" aria-hidden /> Upload document
              </Button>
            ) : null
          }
        >
          {staff.documents && staff.documents.length > 0 ? (
            <div className="divide-y divide-line text-sm">
              {staff.documents.map((doc) => {
                const catMeta = statusBadgeFor(STAFF_DOCUMENT_CATEGORIES, doc.category)
                return (
                  <div key={doc.id} className="py-2.5 flex items-center justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-ink truncate">{doc.title}</span>
                        <Badge tone={catMeta.tone}>{catMeta.label}</Badge>
                      </div>
                      <div className="text-xs text-ink-muted truncate mt-0.5">
                        {doc.original_name} • {formatBytes(doc.size)} • {formatDate(doc.created_at)}
                      </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        aria-label={`Download ${doc.title}`}
                        className="rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink"
                        onClick={() => handleDownloadDoc(doc)}
                        title="Download document"
                      >
                        <Download className="size-4" aria-hidden />
                      </button>
                      {canDeleteDocs ? (
                        <button
                          type="button"
                          aria-label={`Delete ${doc.title}`}
                          className="rounded-lg p-1.5 text-ink-muted hover:bg-red-50 hover:text-red-600"
                          onClick={() => setDeletingDoc(doc)}
                          title="Delete document"
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </button>
                      ) : null}
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-sm text-ink-muted text-center py-6">
              No confidential documents uploaded yet.
            </p>
          )}
        </Card> : null}
      </div>

      {/* Card 4b: Direct Reports / Subordinates */}
      {staff.subordinates && staff.subordinates.length > 0 ? (
        <Card title={`Direct Reports (${staff.subordinates.length})`}>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            {staff.subordinates.map((sub) => (
              <Link
                key={sub.id}
                to={`/staff/${sub.id}`}
                className="flex items-center justify-between rounded-lg border border-line bg-canvas p-3 transition-colors hover:border-brand hover:bg-brand-50/50"
              >
                <div className="min-w-0 pr-2">
                  <div className="font-medium text-ink truncate">
                    {sub.full_name || `${sub.first_name} ${sub.last_name}`}
                  </div>
                  <div className="text-xs text-ink-muted truncate">
                    {sub.job_title} {sub.department ? `• ${sub.department}` : ''}
                  </div>
                </div>
                <Badge tone="blue">{sub.staff_number}</Badge>
              </Link>
            ))}
          </div>
        </Card>
      ) : null}

      {/* Card 5: Compensation & Salary Audit History (Confidential) */}
      {canViewSalary ? (
        <Card
          title="Compensation & Audit History"
          actions={
            canUpdateSalary ? (
              <Button size="sm" onClick={() => setSalaryOpen(true)}>
                <DollarSign className="size-3.5" aria-hidden /> Update compensation
              </Button>
            ) : null
          }
        >
          {/* Current Compensation Summary */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-lg bg-canvas border border-line mb-6">
            <div>
              <div className="text-xs font-medium text-ink-muted">Base salary</div>
              <div className="text-lg font-bold text-ink">
                {staff.salary_amount != null ? `$${Number(staff.salary_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '—'}
              </div>
            </div>
            <div>
              <div className="text-xs font-medium text-ink-muted">Pay frequency</div>
              <div className="text-lg font-bold text-ink capitalize">
                {staff.pay_frequency || 'Monthly'}
              </div>
            </div>
            <div>
              <div className="text-xs font-medium text-ink-muted">Allowances</div>
              <div className="text-lg font-bold text-ink">
                {staff.allowances != null ? `$${Number(staff.allowances).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '$0.00'}
              </div>
            </div>
          </div>

          {/* Salary Change History Log */}
          <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
            Append-only salary history log
          </h4>
          {staff.salary_histories && staff.salary_histories.length > 0 ? (
            <Table minWidth="min-w-[650px]">
              <THead>
                <Tr>
                  <Th>Effective date</Th>
                  <Th>Salary amount</Th>
                  <Th>Allowances</Th>
                  <Th>Frequency</Th>
                  <Th>Changed by</Th>
                  <Th>Reason / Note</Th>
                  <Th>Recorded at</Th>
                </Tr>
              </THead>
              <TBody>
                {staff.salary_histories.map((hist) => (
                  <Tr key={hist.id}>
                    <Td leading className="font-medium text-ink">{formatDate(hist.effective_from) || 'Immediate'}</Td>
                    <Td primary className="font-mono text-ink font-semibold">
                      ${Number(hist.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </Td>
                    <Td className="text-ink">
                      ${Number(hist.allowances || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </Td>
                    <Td className="text-ink-muted capitalize">{hist.pay_frequency || 'monthly'}</Td>
                    <Td className="text-ink">{hist.changed_by?.name || 'Admin'}</Td>
                    <Td className="text-ink-muted text-xs">{hist.note || '—'}</Td>
                    <Td className="text-ink-muted text-xs">{formatDate(hist.created_at)}</Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          ) : (
            <p className="text-sm text-ink-muted text-center py-4">
              No historical salary changes recorded.
            </p>
          )}
        </Card>
      ) : null}

      {/* Modals */}
      <StaffFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        initial={staff}
        onSaved={fetchStaff}
      />

      <StaffSalaryModal
        open={salaryOpen}
        onClose={() => setSalaryOpen(false)}
        staff={staff}
        onSaved={fetchStaff}
      />

      <StaffLinkUserModal
        open={linkOpen}
        onClose={() => setLinkOpen(false)}
        staff={staff}
        onSaved={fetchStaff}
      />

      <StaffDocumentModal
        open={docOpen}
        onClose={() => setDocOpen(false)}
        staff={staff}
        onSaved={fetchStaff}
      />

      <ConfirmDialog
        open={deletingStaff}
        onClose={() => setDeletingStaff(false)}
        onConfirm={confirmDeleteStaff}
        title="Delete staff profile?"
        message={`This will permanently remove ${staff.full_name} from the staff directory.`}
        confirmLabel="Delete profile"
      />

      <ConfirmDialog
        open={unlinkingUser}
        onClose={() => setUnlinkingUser(false)}
        onConfirm={confirmUnlinkUser}
        title="Unlink system user account?"
        message={`This removes the login link between ${staff.full_name} and user @${staff.user?.username}. The user account and employee directory record will both remain, but will no longer be associated.`}
        confirmLabel="Unlink account"
      />

      <ConfirmDialog
        open={Boolean(deletingDoc)}
        onClose={() => setDeletingDoc(null)}
        onConfirm={confirmDeleteDoc}
        title="Delete confidential document?"
        message={`Are you sure you want to delete ${deletingDoc?.title}? This action cannot be undone.`}
        confirmLabel="Delete document"
      />
    </div>
  )
}
