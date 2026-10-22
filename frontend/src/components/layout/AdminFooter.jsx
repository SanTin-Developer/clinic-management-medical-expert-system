import { publicSiteUrl } from '../../constants/sitePreview.js'

const footerLinks = [
  { label: 'Privacy Policy', href: publicSiteUrl('/privacy-policy') },
  { label: 'Terms of Use', href: publicSiteUrl('/terms-of-use') },
  { label: 'Help', href: publicSiteUrl('/contact') },
]

/** Shared footer for authenticated admin pages. */
export default function AdminFooter() {
  return (
    <footer className="shrink-0 border-t border-line bg-surface px-4 py-4 print:hidden sm:px-6">
      <div className="flex flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
        <p className="text-xs text-ink-muted">
          © 2026 RoyalCare Clinic. All rights reserved.
        </p>
        <nav aria-label="Footer navigation" className="flex flex-wrap justify-center gap-x-5 gap-y-2">
          {footerLinks.map((link) => (
            <a
              key={link.label}
              href={link.href}
              className="text-xs font-medium text-ink-muted transition-colors hover:text-brand focus-visible:text-brand"
            >
              {link.label}
            </a>
          ))}
        </nav>
      </div>
    </footer>
  )
}
