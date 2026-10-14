import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { staffApi } from '../../api/staff.js'
import { STAFF_DOCUMENT_CATEGORY_OPTIONS } from '../../utils/status.js'

const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp']
const MAX_FILE_SIZE = 10 * 1024 * 1024 // 10MB

export default function StaffDocumentModal({ open, onClose, staff, onSaved }) {
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState('cv')
  const [file, setFile] = useState(null)
  const [errors, setErrors] = useState({})
  const [uploading, setUploading] = useState(false)

  useEffect(() => {
    if (!open) return
    setTitle('')
    setCategory('cv')
    setFile(null)
    setErrors({})
  }, [open])

  const handleFileChange = (selectedFile) => {
    if (!selectedFile) {
      setFile(null)
      return
    }

    const ext = selectedFile.name.split('.').pop()?.toLowerCase()
    if (!ext || !ALLOWED_EXTENSIONS.includes(ext)) {
      setErrors((e) => ({
        ...e,
        file: `Disallowed file type (.${ext}). Allowed: PDF, DOC, DOCX, JPG, PNG, WEBP.`,
      }))
      setFile(null)
      return
    }

    if (selectedFile.size > MAX_FILE_SIZE) {
      setErrors((e) => ({
        ...e,
        file: 'File size exceeds the 10MB limit.',
      }))
      setFile(null)
      return
    }

    setErrors((e) => {
      const rest = { ...e }
      delete rest.file
      return rest
    })
    setFile(selectedFile)

    // Prepopulate title from file name if empty
    if (!title.trim()) {
      const baseName = selectedFile.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ')
      setTitle(baseName.charAt(0).toUpperCase() + baseName.slice(1))
    }
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!title.trim()) next.title = 'Document title is required.'
    if (!category) next.category = 'Select a category.'
    if (!file) next.file = 'Please select a file to upload.'

    setErrors(next)
    if (Object.keys(next).length > 0) return

    setUploading(true)
    try {
      const formData = new FormData()
      formData.append('title', title.trim())
      formData.append('category', category)
      formData.append('file', file)

      await staffApi.uploadDocument(staff.id, formData)
      toast.success('Document securely uploaded and scanned.')

      setTitle('')
      setCategory('cv')
      setFile(null)
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
      toast.error(err.message || 'Failed to upload document.')
    } finally {
      setUploading(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Upload Confidential Document"
      description={`Securely upload a document for ${staff?.full_name || staff?.staff_number}. Files are scanned and stored on private storage.`}
      size="md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <Field label="Document title" required error={errors.title}>
          <Input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Medical License Certificate 2026"
          />
        </Field>

        <Field label="Document category" required error={errors.category}>
          <Select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
          >
            {STAFF_DOCUMENT_CATEGORY_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </Select>
        </Field>

        <Field
          label="File attachment"
          required
          error={errors.file}
          hint="Allowed types: PDF, DOC, DOCX, JPG, PNG, WEBP. Max size: 10MB."
        >
          <input
            type="file"
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
            onChange={(e) => handleFileChange(e.target.files?.[0])}
            className="block w-full text-sm text-ink-muted file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer"
          />
        </Field>

        <div className="flex justify-end gap-3 pt-3 border-t border-line">
          <Button type="button" variant="outline" onClick={onClose} disabled={uploading}>
            Cancel
          </Button>
          <Button type="submit" disabled={uploading}>
            {uploading ? 'Scanning & Uploading…' : 'Upload document'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
