import { useEffect, useMemo, useState } from 'react'
import { Eye, GripVertical, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import ConfirmDialog from '../ui/ConfirmDialog.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Button from '../ui/Button.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const blankSection = (order) => ({ heading: '', body: '', order, visible: true })
const blankLanguage = () => ({ title: '', introduction: '', sections: [] })
const initialContent = () => ({ en: blankLanguage(), km: blankLanguage() })
const textValue = (value) => typeof value === 'string' ? value : ''

function normalizedSections(sections) {
  if (!Array.isArray(sections)) return []
  return sections.map((section, index) => ({
    heading: textValue(section?.heading),
    body: textValue(section?.body),
    order: Number.isInteger(section?.order) ? section.order : index + 1,
    visible: section?.visible !== false,
  }))
}

function normalizedContent(content, page) {
  const source = content ?? {}
  return {
    en: {
      title: textValue(source.en?.title) || textValue(page?.title),
      introduction: textValue(source.en?.introduction) || textValue(page?.excerpt),
      sections: normalizedSections(source.en?.sections),
    },
    km: {
      title: textValue(source.km?.title),
      introduction: textValue(source.km?.introduction),
      sections: normalizedSections(source.km?.sections),
    },
  }
}

export default function LegalDocumentEditorModal({ open, onClose, onSaved, page, documentName = 'Privacy Policy' }) {
  const [content, setContent] = useState(initialContent)
  const [notes, setNotes] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [previewLanguage, setPreviewLanguage] = useState('en')
  const [publishConfirm, setPublishConfirm] = useState(false)
  const [removeTarget, setRemoveTarget] = useState(null)

  useEffect(() => {
    if (!open) return
    setContent(normalizedContent(page?.policy_content, page))
    setNotes(textValue(page?.internal_notes))
    setErrors({})
    setPreviewLanguage('en')
  }, [open, page])

  const preview = content[previewLanguage]
  const orderedPreview = useMemo(() => [...preview.sections].filter((section) => section.visible && section.heading && section.body)
    .sort((a, b) => a.order - b.order), [preview.sections])

  const updateLanguage = (language, key, value) => setContent((current) => ({
    ...current, [language]: { ...current[language], [key]: value },
  }))
  const updateSection = (language, index, key, value) => setContent((current) => {
    const sections = current[language].sections.map((section, itemIndex) => itemIndex === index ? { ...section, [key]: value } : section)
    return { ...current, [language]: { ...current[language], sections } }
  })
  const addSection = (language) => updateLanguage(language, 'sections', [...content[language].sections, blankSection(content[language].sections.length + 1)])
  const moveSection = (language, index, direction) => {
    const target = index + direction
    if (target < 0 || target >= content[language].sections.length) return
    const sections = [...content[language].sections]
    ;[sections[index], sections[target]] = [sections[target], sections[index]]
    updateLanguage(language, 'sections', sections.map((section, order) => ({ ...section, order: order + 1 })))
  }
  const removeSection = () => {
    const { language, index } = removeTarget
    updateLanguage(language, 'sections', content[language].sections.filter((_, itemIndex) => itemIndex !== index).map((section, order) => ({ ...section, order: order + 1 })))
    setRemoveTarget(null)
  }
  const validate = () => {
    const next = {}
    for (const language of ['en', 'km']) {
      content[language].sections.forEach((section, index) => {
        if ((section.heading && !section.body) || (!section.heading && section.body)) next[`${language}-${index}`] = 'A section needs both a heading and body.'
      })
    }
    setErrors(next)
    return Object.keys(next).length === 0
  }
  const save = async (status) => {
    if (!validate()) return
    setSaving(true)
    try {
      await websiteAdminApi.updatePage(page.id, {
        title: content.en.title.trim() || page.title,
        excerpt: content.en.introduction.trim() || null,
        policy_content: content,
        internal_notes: notes,
        status,
      })
      toast.success(status === 'published' ? `${documentName} published.` : `${documentName} saved as draft.`)
      onSaved?.()
      onClose()
    } catch (error) {
      setErrors({ form: error.message })
    } finally {
      setSaving(false)
    }
  }

  return <>
    <Modal open={open} onClose={onClose} title={`${documentName} editor`} description="Plain text only. Draft changes remain private until published." size="xl"
      footer={<><Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button><Button variant="secondary" onClick={() => save('draft')} loading={saving}>Save draft</Button><Button onClick={() => setPublishConfirm(true)} disabled={saving}>Update published document</Button></>}>
      <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,.72fr)]">
        <form className="space-y-7" onSubmit={(event) => event.preventDefault()} noValidate>
          {errors.form ? <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{errors.form}</p> : null}
          <p className="rounded-xl border border-azure-100 bg-azure-50/60 p-3 text-sm leading-6 text-ink-muted">Last updated is managed by the server when this document is saved. The canonical URL remains <strong>/pages/{page?.slug}</strong>.</p>
          {['en', 'km'].map((language) => <section key={language} className="rounded-2xl border border-line p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3"><h3 className="font-display text-lg font-bold text-ink">{language === 'en' ? 'English content' : 'មាតិកាភាសាខ្មែរ'}</h3><Button type="button" size="sm" variant="outline" onClick={() => addSection(language)}><Plus className="size-4" aria-hidden />Add section</Button></div>
            <div className="mt-4 space-y-4">
              <Field label="Page title" htmlFor={`${language}-title`}><Input id={`${language}-title`} value={content[language].title} onChange={(e) => updateLanguage(language, 'title', e.target.value)} /></Field>
              <Field label="Introduction" htmlFor={`${language}-intro`} hint="A short summary shown below the page title."><Textarea id={`${language}-intro`} rows={3} value={content[language].introduction} onChange={(e) => updateLanguage(language, 'introduction', e.target.value)} /></Field>
              {content[language].sections.map((section, index) => <div key={index} className="rounded-xl border border-line bg-canvas/50 p-3">
                <div className="flex items-center justify-between gap-2"><span className="inline-flex items-center gap-1 text-sm font-semibold text-ink"><GripVertical className="size-4 text-ink-muted" aria-hidden />Section {index + 1}</span><div className="flex gap-1"><Button type="button" size="sm" variant="ghost" onClick={() => moveSection(language, index, -1)} disabled={index === 0}>↑</Button><Button type="button" size="sm" variant="ghost" onClick={() => moveSection(language, index, 1)} disabled={index === content[language].sections.length - 1}>↓</Button><Button type="button" size="sm" variant="ghost" aria-label="Remove section" onClick={() => setRemoveTarget({ language, index })}><Trash2 className="size-4 text-red-600" aria-hidden /></Button></div></div>
                {errors[`${language}-${index}`] ? <p role="alert" className="mt-2 text-xs text-red-700">{errors[`${language}-${index}`]}</p> : null}
                <div className="mt-3 space-y-3"><Field label="Section heading" htmlFor={`${language}-heading-${index}`}><Input id={`${language}-heading-${index}`} value={section.heading} onChange={(e) => updateSection(language, index, 'heading', e.target.value)} /></Field><Field label="Section body" htmlFor={`${language}-body-${index}`}><Textarea id={`${language}-body-${index}`} rows={5} value={section.body} onChange={(e) => updateSection(language, index, 'body', e.target.value)} /></Field><label className="flex items-center gap-2 text-sm text-ink"><input type="checkbox" checked={section.visible} onChange={(e) => updateSection(language, index, 'visible', e.target.checked)} className="size-4 accent-[var(--color-brand)]" />Show this section publicly</label></div>
              </div>)}
            </div>
          </section>)}
          <Field label="Internal administrative notes" htmlFor="policy-notes" hint="Never shown on the public website."><Textarea id="policy-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
        </form>
        <aside className="min-w-0"><div className="sticky top-3 rounded-2xl border border-line bg-surface p-4 shadow-[var(--shadow-card)]"><div className="flex items-center justify-between"><h3 className="inline-flex items-center gap-2 font-display font-bold text-ink"><Eye className="size-4" aria-hidden />Draft preview</h3><div className="inline-flex rounded-lg border border-line p-0.5"><button type="button" onClick={() => setPreviewLanguage('en')} className={`rounded px-2 py-1 text-xs font-semibold ${previewLanguage === 'en' ? 'bg-azure-700 text-white' : 'text-ink-muted'}`}>EN</button><button type="button" onClick={() => setPreviewLanguage('km')} className={`rounded px-2 py-1 text-xs font-semibold ${previewLanguage === 'km' ? 'bg-azure-700 text-white' : 'text-ink-muted'}`}>ខ្មែរ</button></div></div><p className="mt-2 text-xs text-ink-muted">Preview only — it does not publish changes.</p><div className="mt-5 border-t border-line pt-5"><p className="text-xs font-bold uppercase tracking-wider text-azure-700">RoyalCare Clinic</p><h4 className="mt-2 font-display text-2xl font-bold text-ink">{preview.title || documentName}</h4>{preview.introduction ? <p className="mt-3 text-sm leading-6 text-ink-muted">{preview.introduction}</p> : null}{orderedPreview.map((section, index) => <section className="border-b border-line py-4 last:border-0" key={`${section.heading}-${index}`}><h5 className="font-semibold text-ink">{index + 1}. {section.heading}</h5><p className="mt-2 whitespace-pre-line text-sm leading-6 text-ink-muted">{section.body}</p></section>)}</div></div></aside>
      </div>
    </Modal>
    <ConfirmDialog open={publishConfirm} onClose={() => setPublishConfirm(false)} onConfirm={() => { setPublishConfirm(false); save('published') }} title={`Publish ${documentName}?`} message="This will make the saved document content available on the public website." confirmLabel="Publish document" danger={false} loading={saving} />
    <ConfirmDialog open={Boolean(removeTarget)} onClose={() => setRemoveTarget(null)} onConfirm={removeSection} title="Remove document section?" message="This removes the section from this draft. It will not affect the published document until you publish." confirmLabel="Remove section" />
  </>
}
