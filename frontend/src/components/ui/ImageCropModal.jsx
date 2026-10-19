import { useEffect, useState } from 'react'
import { Crop } from 'lucide-react'
import { toast } from 'sonner'
import Cropper from 'react-easy-crop'
import Modal from './Modal.jsx'
import Button from './Button.jsx'
import { Spinner } from './Skeleton.jsx'
import { cn } from '../../utils/cn.js'

/** Output formats the browser can reliably encode from a canvas. */
const KEEP_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const MIME_EXTENSIONS = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
}

const RATIO_PRESETS = [
  { label: 'Original', value: 'original' },
  { label: '1:1', value: 1 },
  { label: '4:3', value: 4 / 3 },
  { label: '16:9', value: 16 / 9 },
]

function basename(source) {
  try {
    const path = new URL(source, window.location.href).pathname
    return decodeURIComponent(path.split('/').filter(Boolean).pop() ?? '')
  } catch {
    return ''
  }
}

function guessMimeType(name) {
  const extension = name.split('.').pop()?.toLowerCase()
  if (extension === 'png' || extension === 'gif') return 'image/png'
  if (extension === 'webp') return 'image/webp'
  if (extension === 'jpg' || extension === 'jpeg') return 'image/jpeg'
  return null
}

function resolveOutputName(name, mimeType) {
  const extension = MIME_EXTENSIONS[mimeType] ?? 'jpg'
  const stem = name.replace(/\.[^.]+$/, '').trim()
  return `${stem || 'cropped'}.${extension}`
}

function loadImage(source) {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('Unable to decode the image.'))
    image.src = source
  })
}

async function cropToArea(source, area, mimeType, fileName) {
  const image = await loadImage(source)
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(area.width))
  canvas.height = Math.max(1, Math.round(area.height))
  const context = canvas.getContext('2d')
  context.drawImage(image, area.x, area.y, area.width, area.height, 0, 0, canvas.width, canvas.height)

  const blob = await new Promise((resolve, reject) => {
    canvas.toBlob(
      (result) => (result ? resolve(result) : reject(new Error('Could not encode the cropped image.'))),
      mimeType,
      0.92,
    )
  })

  return new File([blob], resolveOutputName(fileName, blob.type), { type: blob.type })
}

/**
 * Modal cropper for admin image fields. Exports the selected crop region as a
 * new File so the parent field receives a cropped upload.
 */
export default function ImageCropModal({
  open,
  onClose,
  onCrop,
  src,
  mimeType,
  fileName,
  aspect = 'original',
}) {
  const [cropSrc, setCropSrc] = useState(null)
  const [loading, setLoading] = useState(false)
  const [ratio, setRatio] = useState(aspect)
  const [natural, setNatural] = useState(null)
  const [crop, setCrop] = useState({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [areaPixels, setAreaPixels] = useState(null)
  const [applying, setApplying] = useState(false)

  // Resolve the cropper source. Local previews are already object URLs; hosted
  // images are fetched as a blob first so the canvas is never CORS-tainted.
  useEffect(() => {
    if (!open) {
      setCropSrc(null)
      return undefined
    }

    setRatio(aspect)
    setNatural(null)
    setCrop({ x: 0, y: 0 })
    setZoom(1)
    setAreaPixels(null)

    if (!src) {
      setCropSrc(null)
      return undefined
    }

    const isInline = src.startsWith('blob:') || src.startsWith('data:')
    if (isInline) {
      setCropSrc(src)
      return undefined
    }

    let cancelled = false
    let objectUrl = null
    setLoading(true)
    fetch(src)
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        return response.blob()
      })
      .then((blob) => {
        if (cancelled) return
        objectUrl = URL.createObjectURL(blob)
        setCropSrc(objectUrl)
      })
      .catch(() => {
        // Fall back to the raw URL; the crop step will surface a clear error
        // if the image turns out to be cross-origin tainted.
        if (!cancelled) setCropSrc(src)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [open, src, aspect])

  const activeAspect =
    ratio === 'original' ? (natural ? natural.width / natural.height : 1) : ratio

  const handleRatioChange = (value) => {
    setRatio(value)
    setCrop({ x: 0, y: 0 })
    setZoom(1)
    setAreaPixels(null)
  }

  const applyCrop = async () => {
    if (!cropSrc || !areaPixels) return
    setApplying(true)
    try {
      const sourceName = fileName || basename(src)
      const outputMimeType =
        (KEEP_MIME_TYPES.includes(mimeType) ? mimeType : null) ??
        guessMimeType(sourceName) ??
        'image/jpeg'
      const cropped = await cropToArea(cropSrc, areaPixels, outputMimeType, sourceName)
      onCrop?.(cropped)
    } catch (err) {
      console.error(err)
      toast.error('Could not crop this image. Try removing it and uploading a new one.')
    } finally {
      setApplying(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Crop image"
      description="Drag the image to reposition and use the slider to zoom."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={applying}>
            Cancel
          </Button>
          <Button onClick={applyCrop} loading={applying} disabled={loading || !areaPixels}>
            <Crop className="size-4" aria-hidden />
            Apply crop
          </Button>
        </>
      }
    >
      <div className="relative h-[45vh] min-h-64 w-full overflow-hidden rounded-xl bg-slate-900">
        {cropSrc && !loading ? (
          <Cropper
            key={`${ratio}-${activeAspect.toFixed(4)}`}
            image={cropSrc}
            crop={crop}
            zoom={zoom}
            rotation={0}
            aspect={activeAspect}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={(_area, pixels) => setAreaPixels(pixels)}
            onCropAreaChange={(_area, pixels) => setAreaPixels(pixels)}
            onMediaLoaded={setNatural}
          />
        ) : (
          <div className="absolute inset-0 grid place-items-center">
            <Spinner label="Loading image…" />
          </div>
        )}
      </div>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-1.5">
          {RATIO_PRESETS.map((preset) => (
            <button
              key={preset.label}
              type="button"
              onClick={() => handleRatioChange(preset.value)}
              className={cn(
                'rounded-lg border px-2.5 py-1 text-xs font-semibold transition-colors',
                ratio === preset.value
                  ? 'border-brand bg-brand text-white'
                  : 'border-line bg-surface text-ink-muted hover:text-ink',
              )}
            >
              {preset.label}
            </button>
          ))}
        </div>
        <label className="flex min-w-52 flex-1 items-center gap-2 text-xs font-medium text-ink-muted">
          Zoom
          <input
            type="range"
            min={1}
            max={3}
            step={0.01}
            value={zoom}
            onChange={(event) => setZoom(Number(event.target.value))}
            className="w-full accent-[var(--color-brand)]"
            aria-label="Zoom"
          />
        </label>
      </div>
    </Modal>
  )
}
