import { COMMON_TRANSLATIONS } from './common.js'
import { HOME_TRANSLATIONS } from './home.js'
import { ABOUT_TRANSLATIONS } from './about.js'
import { SERVICES_TRANSLATIONS } from './services.js'
import { TEAM_TRANSLATIONS } from './team.js'
import { GALLERY_TRANSLATIONS } from './gallery.js'
import { NEWS_TRANSLATIONS } from './news.js'
import { CONTACT_TRANSLATIONS } from './contact.js'
import { LEGAL_TRANSLATIONS } from './legal.js'

/**
 * Khmer dictionary. English strings are the keys; the dictionary is split by
 * area so each page's copy stays easy to find and edit.
 */
export const KM_TRANSLATIONS = {
  ...COMMON_TRANSLATIONS,
  ...HOME_TRANSLATIONS,
  ...ABOUT_TRANSLATIONS,
  ...SERVICES_TRANSLATIONS,
  ...TEAM_TRANSLATIONS,
  ...GALLERY_TRANSLATIONS,
  ...NEWS_TRANSLATIONS,
  ...CONTACT_TRANSLATIONS,
  ...LEGAL_TRANSLATIONS,
}
