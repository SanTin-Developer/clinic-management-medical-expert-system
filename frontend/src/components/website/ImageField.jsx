import { useEffect, useState } from 'react'
import FileDropzone from '../ui/FileDropzone.jsx'

const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.webp,.gif'

/**
 * Image picker used across the Website CMS forms. Manages a local preview of a
 * freshly selected File and falls back to the existing hosted image URL.
 */
export default function ImageField({
  id,
  file,
  existingUrl,
  onFile,
  onRemove,
  hint = 'JPG, PNG, WEBP or GIF · up to 10MB',
}) {
  const [preview, setPreview] = useState(null)

  useEffect(() => {
    if (!file) {
      setPreview(null)
      return undefined
    }
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const shown = preview ?? existingUrl

  if (shown) {
    return (
      <div className="relative overflow-hidden rounded-2xl border border-line">
        <img src={shown} alt="Selected preview" className="h-44 w-full object-cover" />
        <button
          type="button"
          onClick={onRemove}
          className="absolute right-2 top-2 rounded-lg bg-black/60 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-black/80"
        >
          Remove
        </button>
      </div>
    )
  }

  return (
    <FileDropzone
      id={id}
      value={file}
      accept={IMAGE_ACCEPT}
      hint={hint}
      onSelect={(selected) => (selected ? onFile(selected) : onRemove())}
    />
  )
}
