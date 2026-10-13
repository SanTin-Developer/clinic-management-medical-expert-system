export default function AuthShell({ children, title, description }) {
  return (
    <>
      <div className="animate-fade-in-up mb-6 flex flex-col items-center text-center">
        <span
          className="grid size-14 select-none place-items-center rounded-2xl bg-white shadow-[var(--shadow-card)] ring-1 ring-line md:hidden"
          onContextMenu={(event) => event.preventDefault()}
          onCopy={(event) => event.preventDefault()}
          onCut={(event) => event.preventDefault()}
        >
          <img
            src="/royalcare-logo.png"
            alt="RoyalCare Clinic"
            draggable={false}
            style={{ WebkitUserDrag: 'none', userSelect: 'none' }}
            className="pointer-events-none size-9 object-contain"
          />
        </span>
        <h1 className="mt-4 text-2xl font-bold tracking-tight text-ink md:mt-0">{title}</h1>
        {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
      </div>
      <div
        className="animate-fade-in-up rounded-2xl border border-line/80 bg-surface/85 p-6 shadow-[0_20px_50px_-26px_rgb(37_99_235_/_0.55)] ring-1 ring-brand/5 backdrop-blur-xl"
        style={{ animationDelay: '80ms' }}
      >
        {children}
      </div>
    </>
  )
}
