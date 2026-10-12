import { APP_NAME } from '../../constants/index.js'
import { cn } from '../../utils/cn.js'

export const DEFAULT_SHOWCASE_IMAGE =
  'https://res.cloudinary.com/drercy9vt/image/upload/v1791811187/doctorImage_nzmi1x.avif'

const DEFAULT_TAGLINES = [
  'Secure access to your clinic workspace',
  'Real-time collaboration for care teams',
  'Built for speed, safety and compliance',
]

export default function SplitLayout({
  children,
  imageUrl = DEFAULT_SHOWCASE_IMAGE,
  logoSrc = '/royalcare-logo.png',
  brand = APP_NAME,
  taglines = DEFAULT_TAGLINES,
  className,
}) {
  return (
    <div className={cn('flex min-h-screen flex-col bg-white md:flex-row', className)}>
      <aside className="relative hidden overflow-hidden md:flex md:w-1/2" aria-hidden>
        <img
          src={imageUrl}
          alt=""
          className="absolute inset-0 size-full object-cover object-center"
        />
        <div className="absolute inset-0 bg-gradient-to-tr from-blue-700/90 via-cyan-600/70 to-cyan-400/40" />
        <div className="absolute inset-0 bg-gradient-to-t from-ink/60 via-transparent to-transparent" />

        <div className="relative z-10 mt-auto flex w-full flex-col gap-5 p-10 lg:p-14">
          <span className="inline-flex w-fit items-center rounded-full bg-white/15 p-2 ring-1 ring-white/30 backdrop-blur-sm">
            <span className="grid size-11 place-items-center rounded-full bg-white shadow-sm">
              <img src={logoSrc} alt={`${brand} logo`} className="size-7 object-contain" />
            </span>
          </span>

          <h2 className="max-w-md text-3xl font-bold leading-tight tracking-tight text-white lg:text-4xl">
            {brand}
          </h2>

          <ul className="space-y-2">
            {taglines.slice(0, 3).map((line) => (
              <li key={line} className="flex items-center gap-2 text-sm text-white/85">
                <span className="size-1.5 shrink-0 rounded-full bg-cyan-200" aria-hidden />
                {line}
              </li>
            ))}
          </ul>
        </div>
      </aside>

      <main className="flex min-h-screen w-full flex-col items-center justify-center bg-white px-5 py-10 sm:px-8 md:w-1/2">
        <div className="w-full max-w-md">{children}</div>
      </main>
    </div>
  )
}
