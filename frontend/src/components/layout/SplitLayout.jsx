import { Fragment } from 'react'
import { APP_NAME } from '../../constants/index.js'
import { cn } from '../../utils/cn.js'

export const DEFAULT_SHOWCASE_IMAGE =
  'https://res.cloudinary.com/drercy9vt/image/upload/v1791811187/doctorImage_nzmi1x.avif'

const DEFAULT_TAGLINES = [
  'Secure access to your clinic workspace',
  'Real-time collaboration for care teams',
  'Built for speed, safety and compliance',
]

export const DEFAULT_CREDIT =
  'Designed and developed by SanTin Dev with a focus on quality, security, and usability.'

export default function SplitLayout({
  children,
  imageUrl = DEFAULT_SHOWCASE_IMAGE,
  logoSrc = '/royalcare-logo.png',
  brand = APP_NAME,
  taglines = DEFAULT_TAGLINES,
  credit = DEFAULT_CREDIT,
  className,
}) {
  return (
    <div className={cn('flex min-h-screen flex-col bg-canvas md:flex-row', className)}>
      <aside className="relative hidden overflow-hidden bg-brand-darker md:flex md:w-1/2" aria-hidden>
        <img
          src={imageUrl}
          alt=""
          draggable={false}
          className="absolute inset-0 size-full object-cover object-center"
        />

        {/* Colour wash + depth */}
        <div className="absolute inset-0 bg-gradient-to-tr from-brand-darker/95 via-brand/75 to-cyan-500/45" />
        <div className="absolute inset-0 bg-gradient-to-t from-ink/75 via-ink/10 to-transparent" />

        {/* Soft animated glow orbs */}
        <div className="animate-aurora pointer-events-none absolute -left-24 -top-24 size-[26rem] rounded-full bg-cyan-300/30 blur-3xl" />
        <div className="animate-float-slow pointer-events-none absolute -right-16 top-1/3 size-80 rounded-full bg-blue-400/40 blur-3xl" />
        <div className="animate-float-reverse pointer-events-none absolute -bottom-20 left-1/4 size-72 rounded-full bg-indigo-300/30 blur-3xl" />

        {/* Decorative grid */}
        <div className="auth-grid pointer-events-none absolute inset-0 opacity-70" />

        <div className="relative z-10 mt-auto flex w-full flex-col gap-6 p-10 lg:p-14">
          <span
            role="img"
            aria-label={`${brand} logo`}
            onContextMenu={(event) => event.preventDefault()}
            onCopy={(event) => event.preventDefault()}
            onCut={(event) => event.preventDefault()}
            className="animate-fade-in-up inline-flex w-fit select-none items-center rounded-2xl bg-white/10 p-2 ring-1 ring-white/25 backdrop-blur-md"
          >
            <span className="pointer-events-none grid size-11 place-items-center rounded-xl bg-white shadow-sm">
              <img
                src={logoSrc}
                alt=""
                draggable={false}
                style={{ WebkitUserDrag: 'none', userSelect: 'none' }}
                className="size-7 object-contain"
              />
            </span>
          </span>

          <h2
            className="animate-fade-in-up max-w-md text-3xl font-bold leading-tight tracking-tight text-white lg:text-4xl"
            style={{ animationDelay: '60ms' }}
          >
            {brand}
            <span className="mt-2 block text-lg font-medium text-cyan-100/90 lg:text-xl">
              A calmer way to run your clinic.
            </span>
          </h2>

          <ul className="animate-fade-in-up space-y-2.5" style={{ animationDelay: '120ms' }}>
            {taglines.slice(0, 3).map((line) => (
              <li key={line} className="flex items-center gap-2.5 text-sm text-white/85">
                <span className="grid size-5 shrink-0 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                  <span className="size-1.5 rounded-full bg-cyan-200" aria-hidden />
                </span>
                {line}
              </li>
            ))}
          </ul>

          {credit ? (
            <p
              className="animate-fade-in-up mt-2 border-t border-white/15 pt-5 text-xs leading-relaxed text-white/70"
              style={{ animationDelay: '180ms' }}
            >
              {credit.split('SanTin Dev').map((part, index, parts) => (
                <Fragment key={index}>
                  {part}
                  {index < parts.length - 1 ? (
                    <span className="font-semibold text-white">SanTin Dev</span>
                  ) : null}
                </Fragment>
              ))}
            </p>
          ) : null}
        </div>
      </aside>

      <main className="relative flex min-h-screen w-full flex-col items-center justify-center bg-canvas px-5 py-10 sm:px-8 md:w-1/2">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="absolute -right-24 -top-24 size-72 rounded-full bg-brand-soft/70 blur-3xl" />
          <div className="absolute -bottom-24 -left-20 size-72 rounded-full bg-brand-soft/60 blur-3xl" />
        </div>
        <div className="relative w-full max-w-md">{children}</div>
      </main>
    </div>
  )
}
