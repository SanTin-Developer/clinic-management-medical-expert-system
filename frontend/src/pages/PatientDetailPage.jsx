import { useCallback, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  ArrowLeft,
  Download,
  FileText,
  Pencil,
  Trash2,
  Upload,
} from 'lucide-react'
import { patientApi } from '../api/patients.js'
import { documentApi, downloadDocument } from '../api/documents.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Modal from '../components/ui/Modal.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import Select from '../components/ui/Select.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import PatientFormModal from '../components/patients/PatientFormModal.jsx'
import {
  ageFromDateOfBirth,
  formatBytes,
  formatDate,
  patientFullName,
} from '../utils/format.js'
import { DOCUMENT_CATEGORIES, DOCUMENT_CATEGORY_OPTIONS } from '../utils/status.js'

export default function PatientDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const navigate = useNavigate()

  const patientQuery = useQuery({
    queryKey: ['patient', id],
    queryFn: () => patientApi.get(id),
  })

  const documentsQuery = useQuery({
    queryKey: ['documents', id],
    queryFn: () => documentApi.list(id),
  })

  const [editOpen, setEditOpen] = useState(false)
  const [uploadOpen, setUploadOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [docPending, setDocPending] = useState(null)

  const patient = patientQuery.data
  const documents = documentsQuery.data ?? []
  const age = patient ? ageFromDateOfBirth(patient.date_of_birth) : null

  const refetchAll = useCallback(() => {
    patientQuery.refetch()
    documentsQuery.refetch()
  }, [patientQuery, documentsQuery])

  const confirmDelete = async () => {
    try {
      await patientApi.remove(id)
      toast.success('Patient deleted.')
      navigate('/patients')
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDocDelete = async () => {
    if (!docPending) return
    try {
      await documentApi.remove(docPending.id)
      toast.success('Document deleted.')
      setDocPending(null)
      documentsQuery.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <PageHeader
        title={patient ? patientFullName(patient) : 'Patient'}
        description={patient ? patient.patient_number : 'Loading…'}
        breadcrumb={[{ label: 'Patients', to: '/patients' }, { label: 'Profile' }]}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/patients')}>
              <ArrowLeft className="size-4" aria-hidden /> Back
            </Button>
            {can('patients.update') ? (
              <Button onClick={() => setEditOpen(true)}>
                <Pencil className="size-4" aria-hidden /> Edit
              </Button>
            ) : null}
          </>
        }
      />

      {patientQuery.isLoading ? (
        <CardSkeleton rows={5} />
      ) : patientQuery.isError ? (
        <ErrorState
          title="Could not load patient"
          message={patientQuery.error.message}
          onRetry={() => patientQuery.refetch()}
        />
      ) : (
        <div className="space-y-6">
          <Card title="Patient information">
            <dl className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
              {[
                ['Gender', patient.gender ? patient.gender.charAt(0).toUpperCase() + patient.gender.slice(1) : '—'],
                ['Age', age !== null ? `${age} years old` : '—'],
                ['Date of birth', formatDate(patient.date_of_birth)],
                ['Phone', patient.phone_number ?? '—'],
                ['Email', patient.email ?? '—'],
                ['Blood type', patient.blood_type ?? '—'],
                ['Address', patient.address ?? '—'],
                ['Emergency contact', patient.emergency_contact_name ?? '—'],
                ['Emergency phone', patient.emergency_contact_phone ?? '—'],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt className="text-xs font-medium uppercase tracking-wide text-ink-muted">{label}</dt>
                  <dd className="mt-1 text-sm font-medium text-ink">{value}</dd>
                </div>
              ))}
            </dl>
            <div className="mt-6 grid grid-cols-1 gap-6 border-t border-line pt-5 sm:grid-cols-2">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Allergies</p>
                <p className="mt-1 text-sm text-ink">{patient.allergies || 'None recorded'}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Notes</p>
                <p className="mt-1 text-sm text-ink">{patient.notes || 'No notes'}</p>
              </div>
            </div>
            {can('patients.delete') ? (
              <div className="mt-6 flex justify-end border-t border-line pt-4">
                <Button variant="danger" size="sm" onClick={() => setDeleting(true)}>
                  <Trash2 className="size-4" aria-hidden /> Delete patient
                </Button>
              </div>
            ) : null}
          </Card>

          <Card
            title="Documents"
            subtitle="Lab results, imaging, referrals, consent forms and more."
            actions={
              can('documents.manage') ? (
                <Button size="sm" onClick={() => setUploadOpen(true)}>
                  <Upload className="size-4" aria-hidden /> Upload
                </Button>
              ) : null
            }
          >
            {documentsQuery.isLoading ? (
              <CardSkeleton rows={3} />
            ) : documentsQuery.isError ? (
              <ErrorState message={documentsQuery.error.message} onRetry={() => documentsQuery.refetch()} />
            ) : documents.length === 0 ? (
              <EmptyState
                title="No documents"
                message="Upload lab results, imaging, referrals or consent forms for this patient."
              />
            ) : (
              <ul className="divide-y divide-line">
                {documents.map((doc) => {
                  const cat = DOCUMENT_CATEGORIES[doc.category]
                  return (
                    <li key={doc.id} className="flex flex-wrap items-center gap-3 py-3">
                      <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                        <FileText className="size-5" aria-hidden />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-ink">{doc.title}</p>
                        <p className="text-xs text-ink-muted">
                          {doc.original_name} · {formatBytes(doc.size)} · uploaded by {doc.uploaded_by?.name ?? '—'} on {formatDate(doc.created_at)}
                        </p>
                      </div>
                      <Badge tone={cat?.tone ?? 'gray'}>{cat?.label ?? doc.category}</Badge>
                      <div className="flex gap-1">
                        <button
                          type="button"
                          aria-label={`Download ${doc.title}`}
                          className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                          onClick={() =>
                            downloadDocument(doc.id, doc.original_name).catch((err) =>
                              toast.error(err.message),
                            )
                          }
                        >
                          <Download className="size-4" aria-hidden />
                        </button>
                        {can('documents.manage') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${doc.title}`}
                            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDocPending(doc)}
                          >
                            <Trash2 className="size-4" aria-hidden />
                          </button>
                        ) : null}
                      </div>
                    </li>
                  )
                })}
              </ul>
            )}
          </Card>
        </div>
      )}

      <PatientFormModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        initial={patient}
        onSaved={refetchAll}
      />

      <DocumentUploadModal
        patientId={id}
        open={uploadOpen}
        onClose={() => setUploadOpen(false)}
        onUploaded={() => documentsQuery.refetch()}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        title="Delete patient?"
        message="This permanently deletes the patient record and orphans their clinical records. This cannot be undone."
        confirmLabel="Delete"
      />

      <ConfirmDialog
        open={Boolean(docPending)}
        onClose={() => setDocPending(null)}
        onConfirm={confirmDocDelete}
        title="Delete document?"
        message={docPending ? `This removes "${docPending.title}" permanently.` : 'This removes the document permanently.'}
        confirmLabel="Delete"
      />
    </div>
  )
}

function DocumentUploadModal({ patientId, open, onClose, onUploaded }) {
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState('')
  const [file, setFile] = useState(null)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const reset = () => {
    setTitle('')
    setCategory('')
    setFile(null)
    setErrors({})
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!title.trim()) next.title = 'Required.'
    if (!category) next.category = 'Select a category.'
    if (!file) next.file = 'Choose a file to upload.'
    else if (file.size > 10 * 1024 * 1024) next.file = 'File must be 10 MB or smaller.'
    else if (!/^(pdf|jpe?g|png|webp|docx?)$/i.test(file.name.split('.').pop() ?? '')) {
      next.file = 'Allowed types: PDF, JPG, PNG, WEBP, DOC, DOCX.'
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const formData = new FormData()
      formData.append('title', title.trim())
      formData.append('category', category)
      formData.append('file', file)
      await documentApi.upload(patientId, formData)
      toast.success('Document uploaded.')
      reset()
      onUploaded?.()
      onClose()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Upload document"
      description="Max 10 MB. Allowed types: PDF, JPG, PNG, WEBP, DOC, DOCX."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="upload-doc" loading={saving}>
            Upload
          </Button>
        </>
      }
    >
      <form id="upload-doc" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Document title" htmlFor="doc-title" required error={errors.title}>
          <Input id="doc-title" value={title} onChange={(e) => setTitle(e.target.value)} invalid={Boolean(errors.title)} />
        </Field>
        <Field label="Category" htmlFor="doc-category" required error={errors.category}>
          <Select id="doc-category" value={category} onChange={(e) => setCategory(e.target.value)} invalid={Boolean(errors.category)}>
            <option value="">Select…</option>
            {DOCUMENT_CATEGORY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
        </Field>
        <Field label="File" htmlFor="doc-file" required error={errors.file}>
          <Input
            id="doc-file"
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/jpeg,image/png,image/webp"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            invalid={Boolean(errors.file)}
          />
        </Field>
      </form>
    </Modal>
  )
}