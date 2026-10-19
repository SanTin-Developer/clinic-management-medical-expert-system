export const APP_NAME = 'RoyalCare Clinic'

/** Public routes and layout-level constants (not CMS-editable content). */

/**
 * Compact primary navigation: logo left, these centered, CTA right.
 */
export const PRIMARY_NAV = [
  { to: '/home', label: 'Home' },
  { to: '/about', label: 'About Us' },
  { to: '/services', label: 'Services' },
  { to: '/health-news', label: 'News & Health' },
  { to: '/contact', label: 'Contact Us' },
]

/** Secondary destinations surfaced in the footer. */
export const SECONDARY_NAV = [
  { to: '/team', label: 'Our Team' },
  { to: '/gallery', label: 'Gallery' },
]

/** Full link set used by the footer. */
export const FOOTER_LINKS = [...PRIMARY_NAV.slice(1), ...SECONDARY_NAV]

/**
 * Developer-locked design imagery shipped with the app. These are layout
 * assets, not CMS content. An admin-supplied hero photo overrides
 * DEFAULT_HERO_IMAGE through the CMS `hero_image` field (settings.hero_media).
 */
export const DEFAULT_HERO_IMAGE = '/media/hero-background.png'
export const ABOUT_CLINIC_IMAGE = '/media/about-clinic.jpg'

/** Default hero copy, used when the CMS provides no hero_title/hero_subtitle. */
export const HERO_EYEBROW = 'Your Health, Our Priority'
export const HERO_HEADING = ['Better Health', 'Brighter Tomorrow']
export const HERO_DESCRIPTION =
  'At RoyalCare Clinic, we provide compassionate, high-quality healthcare with modern facilities and expert medical professionals.'

/**
 * Green highlight line under the hero subtitle. Normally the CMS "About
 * summary" (settings.about_summary) is shown here; this constant is only the
 * fallback when that field is empty.
 */
export const HERO_HIGHLIGHT = 'Open 24 hours a day, 7 days a week — your health never waits.'

/**
 * ⚠️ UNVERIFIED REFERENCE COPY — NOT CONFIRMED CLINIC STATISTICS.
 * These figures reproduce the design reference only. They have not been
 * verified against real clinic data. Confirm them (or replace with CMS-backed
 * values) before publishing; see README → "Unverified content".
 */
export const HERO_STATS = [
  { key: 'patients', title: 'Trusted by', value: '10,000+', label: 'Happy Patients' },
  { key: 'doctors', title: 'Expert Doctors', value: '50+', label: 'Specialists' },
  { key: 'hours', title: 'Open Daily', value: '24 Hours', label: 'Your Health, Always' },
]

/**
 * ⚠️ STYLED PARTNER MARKS — NOT VERIFIED PARTNERSHIPS.
 * These are design-reference partner logos (icon + wordmark), not confirmed
 * partners. Replace with real partners (ideally CMS-backed) before publishing.
 * See README → "Unverified content".
 */
export const PARTNER_PLACEHOLDERS = [
  { key: 'p1', label: 'PharmaHealth', icon: 'leaf' },
  { key: 'p2', label: 'MediCare', icon: 'plus' },
  { key: 'p3', label: 'LifePlus', icon: 'heart' },
  { key: 'p4', label: 'HealthFirst', icon: 'leaf' },
  { key: 'p5', label: 'WellnessCo', icon: 'wave' },
]
