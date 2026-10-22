import { useEffect, useState } from 'react'
import { Crop } from 'lucide-react'
import ConfirmDialog from './ConfirmDialog.jsx'
import FileDropzone from './FileDropzone.jsx'
import ImageCropModal from './ImageCropModal.jsx'

const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.webp,.gif'

/**
 * Shared image picker for admin forms. Manages a local preview of a freshly
 * selected File and falls back to the existing hosted image URL. The preview
 * shows the whole image (uncropped) and offers a crop tool so the upload can
 * be framed before it is saved.
 */
export default function ImageField({
  id,
  file,
  existingUrl,
  onFile,
  onRemove,
  aspect,
  accept = IMAGE_ACCEPT,
  hint = 'JPG, PNG, WEBP or GIF · up to 10MB',
}) {
  const [preview, setPreview] = useState(null)
  const [cropping, setCropping] = useState(false)
  const [confirmingRemove, setConfirmingRemove] = useState(false)

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
      <>
        <div className="relative overflow-hidden rounded-2xl border border-line bg-canvas">
          <img src={shown} alt="Selected preview" className="h-48 w-full object-contain" />
          <div className="absolute right-2 top-2 flex gap-1.5">
            <button
              type="button"
              onClick={() => setCropping(true)}
              className="flex items-center gap-1 rounded-lg bg-black/60 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-black/80"
            >
              <Crop className="size-3.5" aria-hidden />
              Crop
            </button>
            <button
              type="button"
              onClick={() => (existingUrl && !file ? setConfirmingRemove(true) : onRemove?.())}
              className="rounded-lg bg-black/60 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-black/80"
            >
              Remove
            </button>
          </div>
        </div>
        {cropping ? (
          <ImageCropModal
            open={cropping}
            src={shown}
            mimeType={file?.type}
            fileName={file?.name}
            aspect={aspect}
            onClose={() => setCropping(false)}
            onCrop={(cropped) => {
              setCropping(false)
              onFile?.(cropped)
            }}
          />
        ) : null}
        <ConfirmDialog
          open={confirmingRemove}
          onClose={() => setConfirmingRemove(false)}
          onConfirm={() => {
            setConfirmingRemove(false)
            onRemove?.()
          }}
          title="Remove image from this record?"
          message="Saving the form detaches the image. If no other record uses it, it is then permanently deleted from Cloudinary."
          confirmLabel="Remove image"
        />
      </>
    )
  }

  return (
    <FileDropzone
      id={id}
      value={file}
      accept={accept}
      hint={hint}
      onSelect={(selected) => (selected ? onFile(selected) : onRemove())}
    />
  )
}
