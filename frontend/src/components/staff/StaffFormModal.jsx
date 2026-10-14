import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import FileDropzone from '../ui/FileDropzone.jsx'
import { staffApi } from '../../api/staff.js'
import { useAuth } from '../../hooks/useAuth.js'
import {
  GENDER_OPTIONS,
  EMPLOYMENT_STATUS_OPTIONS,
  EMPLOYMENT_TYPE_OPTIONS,
  PAY_FREQUENCY_OPTIONS,
} from '../../utils/status.js'

const BASE_FORM = {
  first_name: '',
  last_name: '',
  gender: '',
  date_of_birth: '',
  phone: '',
  email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  job_title: '',
  department: '',
  employment_type: 'full_time',
  employment_status: 'employed',
  hire_date: '',
  work_schedule: '',
  supervisor_id: '',
  education: '',
  qualifications: '',
  license_number: '',
  license_expiry: '',
  salary_amount: '',
  pay_frequency: 'monthly',
  allowances: '',
}

const ALLOWED_CV_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp']
const MAX_CV_SIZE = 10 * 1024 * 1024 // 10MB

export default function StaffFormModal({ open, onClose, initial, onSaved }) {
  const { can } = useAuth()
  const editing = Boolean(initial?.id)
  const canManageSalary = can('staff.salary.update')

  const [form, setForm] = useState(BASE_FORM)
  const [cvFile, setCvFile] = useState(null)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [supervisors, setSupervisors] = useState([])

  useEffect(() => {
    if (!open) return

    if (initial) {
      setForm({
        ...BASE_FORM,
        ...initial,
        date_of_birth: initial.date_of_birth ? String(initial.date_of_birth).slice(0, 10) : '',
        hire_date: initial.hire_date ? String(initial.hire_date).slice(0, 10) : '',
        license_expiry: initial.license_expiry ? String(initial.license_expiry).slice(0, 10) : '',
        supervisor_id: initial.supervisor_id ? String(initial.supervisor_id) : '',
        salary_amount: initial.salary_amount != null ? String(initial.salary_amount) : '',
        allowances: initial.allowances != null ? String(initial.allowances) : '',
      })
    } else {
      setForm(BASE_FORM)
    }
    setCvFile(null)
    setErrors({})

    staffApi.list({ per_page: 100 })
      .then((res) => {
        const list = res.data ?? []
        // Filter out self when editing
        setSupervisors(
          list
            .filter((s) => !initial || s.id !== initial.id)
            .map((s) => ({
              value: String(s.id),
              label: `${s.full_name || `${s.first_name} ${s.last_name}`} (${s.staff_number} - ${s.job_title || s.department || 'Staff'})`,
            }))
        )
      })
      .catch(() => {})
  }, [open, initial])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleCvChange = (selectedFile) => {
    if (!selectedFile) {
      setCvFile(null)
      return
    }

    const ext = selectedFile.name.split('.').pop()?.toLowerCase()
    if (!ext || !ALLOWED_CV_EXTENSIONS.includes(ext)) {
      setErrors((e) => ({
        ...e,
        cv: `Disallowed file type (.${ext}). Allowed: PDF, DOC, DOCX, JPG, PNG, WEBP.`,
      }))
      setCvFile(null)
      return
    }

    if (selectedFile.size > MAX_CV_SIZE) {
      setErrors((e) => ({ ...e, cv: 'File size exceeds the 10MB limit.' }))
      setCvFile(null)
      return
    }

    setErrors((e) => {
      const rest = { ...e }
      delete rest.cv
      return rest
    })
    setCvFile(selectedFile)
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.first_name.trim()) next.first_name = 'First name is required.'
    if (!form.last_name.trim()) next.last_name = 'Last name is required.'
    if (form.email && !/^\S+@\S+\.\S+$/.test(form.email.trim())) next.email = 'Enter a valid email address.'
    if (form.date_of_birth && form.date_of_birth >= new Date().toISOString().slice(0, 10)) {
      next.date_of_birth = 'Date of birth must be in the past.'
    }
    if (errors.cv) next.cv = errors.cv

    setErrors(next)
    if (Object.keys(next).length > 0) return

    setSaving(true)
    try {
      const payload = {
        ...form,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email?.trim() || null,
        phone: form.phone?.trim() || null,
        gender: form.gender || null,
        date_of_birth: form.date_of_birth || null,
        address: form.address?.trim() || null,
        emergency_contact_name: form.emergency_contact_name?.trim() || null,
        emergency_contact_phone: form.emergency_contact_phone?.trim() || null,
        job_title: form.job_title?.trim() || null,
        department: form.department?.trim() || null,
        employment_type: form.employment_type || null,
        employment_status: form.employment_status || 'employed',
        hire_date: form.hire_date || null,
        work_schedule: form.work_schedule?.trim() || null,
        supervisor_id: form.supervisor_id ? Number(form.supervisor_id) : null,
        education: form.education?.trim() || null,
        qualifications: form.qualifications?.trim() || null,
        license_number: form.license_number?.trim() || null,
        license_expiry: form.license_expiry || null,
      }

      delete payload.staff_number

      if (canManageSalary && form.salary_amount !== '') {
        payload.salary_amount = Number(form.salary_amount)
        payload.pay_frequency = form.pay_frequency || 'monthly'
        payload.allowances = form.allowances !== '' ? Number(form.allowances) : 0
      } else if (!canManageSalary) {
        delete payload.salary_amount
        delete payload.pay_frequency
        delete payload.allowances
      }

      if (editing) {
        await staffApi.update(initial.id, payload)
        toast.success('Staff profile updated successfully.')
      } else {
        await staffApi.create(payload, cvFile)
        toast.success('Staff profile created successfully.')
      }

      onSaved?.()
      onClose()
    } catch (err) {
      if (err.payload?.errors) {
        const nextErrors = {}
        for (const [key, val] of Object.entries(err.payload.errors)) {
          nextErrors[key] = Array.isArray(val) ? val[0] : String(val)
        }
        setErrors(nextErrors)
      }
      toast.error(err.message || 'Failed to save staff profile.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? `Edit Staff: ${initial?.full_name ?? initial?.first_name}` : 'New Staff Member'}
      description="Record clinic employee details, employment status, credentials, and contact info."
      size="xl"
    >
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Identity & Contact */}
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
            Personal & Contact Information
          </h4>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="First name" required error={errors.first_name}>
              <Input
                value={form.first_name}
                onChange={(e) => set('first_name')(e.target.value)}
                placeholder="Jane"
              />
            </Field>

            <Field label="Last name" required error={errors.last_name}>
              <Input
                value={form.last_name}
                onChange={(e) => set('last_name')(e.target.value)}
                placeholder="Doe"
              />
            </Field>

            <Field label="Gender">
              <Select
                value={form.gender}
                onChange={(event) => set('gender')(event.target.value)}
              >
                {[{ value: '', label: 'Select gender…' }, ...GENDER_OPTIONS].map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </Select>
            </Field>

            <Field label="Date of birth" error={errors.date_of_birth}>
              <Input
                type="date"
                value={form.date_of_birth}
                onChange={(e) => set('date_of_birth')(e.target.value)}
              />
            </Field>

            <Field label="Phone number">
              <Input
                value={form.phone}
                onChange={(e) => set('phone')(e.target.value)}
                placeholder="+1 555-0100"
              />
            </Field>

            <Field label="Work / Contact email" error={errors.email}>
              <Input
                type="email"
                value={form.email}
                onChange={(e) => set('email')(e.target.value)}
                placeholder="jane.doe@clinic.local"
              />
            </Field>

            <Field label="Residential address">
              <Input
                value={form.address}
                onChange={(e) => set('address')(e.target.value)}
                placeholder="123 Health Ave, Suite 4"
              />
            </Field>
          </div>
        </div>

        {/* Employment Information */}
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
            Employment & Position
          </h4>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Job title">
              <Input
                value={form.job_title}
                onChange={(e) => set('job_title')(e.target.value)}
                placeholder="Senior Physician / Receptionist"
              />
            </Field>

            <Field label="Department">
              <Input
                value={form.department}
                onChange={(e) => set('department')(e.target.value)}
                placeholder="Cardiology / Pharmacy / Front Desk"
              />
            </Field>

            <Field label="Employment type">
              <Select
                value={form.employment_type}
                onChange={(event) => set('employment_type')(event.target.value)}
              >
                {EMPLOYMENT_TYPE_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </Select>
            </Field>

            <Field label="Employment status" hint="Independent of system account status">
              <Select
                value={form.employment_status}
                onChange={(event) => set('employment_status')(event.target.value)}
              >
                {EMPLOYMENT_STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </Select>
            </Field>

            <Field label="Hire date">
              <Input
                type="date"
                value={form.hire_date}
                onChange={(e) => set('hire_date')(e.target.value)}
              />
            </Field>

            <Field label="Work schedule / Shifts">
              <Input
                value={form.work_schedule}
                onChange={(e) => set('work_schedule')(e.target.value)}
                placeholder="Mon-Fri 08:00 - 17:00"
              />
            </Field>

            <div className="sm:col-span-2">
              <Field label="Supervisor / Reports to">
                <Select
                  value={form.supervisor_id}
                  onChange={(event) => set('supervisor_id')(event.target.value)}
                >
                  {[{ value: '', label: 'None (Direct Report / Top Level)' }, ...supervisors].map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </Select>
              </Field>
            </div>
          </div>
        </div>

        {/* Credentials & Qualifications */}
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
            Credentials & Licenses
          </h4>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Medical license number">
              <Input
                value={form.license_number}
                onChange={(e) => set('license_number')(e.target.value)}
                placeholder="MED-LIC-98765"
              />
            </Field>

            <Field label="License expiry date">
              <Input
                type="date"
                value={form.license_expiry}
                onChange={(e) => set('license_expiry')(e.target.value)}
              />
            </Field>

            <div className="sm:col-span-2">
              <Field label="Education & Degree">
                <Input
                  value={form.education}
                  onChange={(e) => set('education')(e.target.value)}
                  placeholder="MD, Johns Hopkins University"
                />
              </Field>
            </div>

            <div className="sm:col-span-2">
              <Field label="Qualifications & Certifications">
                <Textarea
                  value={form.qualifications}
                  onChange={(e) => set('qualifications')(e.target.value)}
                  placeholder="Board Certified in Internal Medicine, ACLS Certified..."
                  rows={2}
                />
              </Field>
            </div>
          </div>
        </div>

        {/* Emergency Contact */}
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
            Emergency Contact
          </h4>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Contact name">
              <Input
                value={form.emergency_contact_name}
                onChange={(e) => set('emergency_contact_name')(e.target.value)}
                placeholder="John Doe (Spouse)"
              />
            </Field>

            <Field label="Contact phone">
              <Input
                value={form.emergency_contact_phone}
                onChange={(e) => set('emergency_contact_phone')(e.target.value)}
                placeholder="+1 555-0199"
              />
            </Field>
          </div>
        </div>

        {/* Documents */}
        {!editing ? (
          <div>
            <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
              Documents
            </h4>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <Field label="CV / Résumé (optional)" error={errors.cv}>
                  <FileDropzone
                    id="staff-cv-file"
                    value={cvFile}
                    onSelect={handleCvChange}
                    hint="PDF, DOC, DOCX, JPG, PNG, WEBP · up to 10MB · scanned & stored privately"
                  />
                </Field>
              </div>
            </div>
          </div>
        ) : null}

        {/* Initial Compensation (if user is authorized to manage salary) */}
        {canManageSalary && !editing ? (
          <div>
            <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-muted mb-3">
              Initial Compensation (Confidential)
            </h4>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Field label="Salary amount ($)">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={form.salary_amount}
                  onChange={(e) => set('salary_amount')(e.target.value)}
                  placeholder="5000.00"
                />
              </Field>

              <Field label="Pay frequency">
                <Select
                  value={form.pay_frequency}
                  onChange={(event) => set('pay_frequency')(event.target.value)}
                >
                  {PAY_FREQUENCY_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </Select>
              </Field>

              <Field label="Allowances ($)">
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  value={form.allowances}
                  onChange={(e) => set('allowances')(e.target.value)}
                  placeholder="250.00"
                />
              </Field>
            </div>
          </div>
        ) : null}

        <div className="flex justify-end gap-3 pt-4 border-t border-line">
          <Button type="button" variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : editing ? 'Save changes' : 'Create staff member'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
