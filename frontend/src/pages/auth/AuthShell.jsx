import SplitLayout from '../../components/layout/SplitLayout.jsx'

export default function AuthShell({ children, title, description }) {
  return (
    <SplitLayout>
      <div className="mb-6 flex flex-col items-center text-center">
        <img
          src="/royalcare-logo.png"
          alt="RoyalCare Clinic"
          className="size-14 rounded-2xl object-contain md:hidden"
        />
        <h1 className="mt-4 text-2xl font-bold tracking-tight text-ink md:mt-0">{title}</h1>
        {description ? (
          <p className="mt-1 text-sm text-ink-muted">{description}</p>
        ) : null}
      </div>
      <div className="rounded-2xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
        {children}
      </div>
    </SplitLayout>
  )
}
