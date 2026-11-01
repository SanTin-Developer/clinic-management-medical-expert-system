import { publicSiteUrl } from '../../constants/sitePreview.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const CURRENT_YEAR = new Date().getFullYear()

const footerLinks = [
  { label: 'Privacy Policy', href: publicSiteUrl('/privacy-policy') },
  { label: 'Terms of Use', href: publicSiteUrl('/terms-of-use') },
  { label: 'Help', href: publicSiteUrl('/contact') },
]

/** Shared footer for authenticated admin pages. */
export default function AdminFooter() {
  const { t } = useLanguage()

  return (
    <footer className="hidden shrink-0 border-t border-line bg-surface px-4 py-4 print:hidden sm:px-6 md:block">
      <div className="flex flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
        <p className="text-xs text-ink-muted">
          {t('© {year} RoyalCare Clinic. All rights reserved.', { year: CURRENT_YEAR })}
        </p>
        <nav aria-label={t('Footer navigation')} className="flex flex-wrap justify-center gap-x-5 gap-y-2">
          {footerLinks.map((link) => (
            <a
              key={link.label}
              href={link.href}
              className="text-xs font-medium text-ink-muted transition-colors hover:text-brand focus-visible:text-brand"
            >
              {t(link.label)}
            </a>
          ))}
        </nav>
      </div>
    </footer>
  )
}
