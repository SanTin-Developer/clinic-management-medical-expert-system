import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../api/website.js'
import { translate } from '../i18n/index.js'
import { useLanguage } from '../hooks/useLanguage.js'

/**
 * Turns the bootstrap `content_blocks` array into a key → block map so every
 * page and layout can look up its admin-editable copy. Blocks the admin has
 * not saved yet simply fall back to the design defaults.
 */
export function indexBlocks(blocks) {
  const map = {}
  for (const block of blocks ?? []) {
    map[block.key] = block
  }
  return map
}

/**
 * Reads a CMS field in the visitor's language.
 *
 * Every translatable column has a `_km` sibling. The Khmer value wins only
 * when it is non-empty; otherwise the English column is shown, so partially
 * translated content never renders a blank space.
 */
export function localized(record, field, language) {
  if (!record) return ''

  if (language === 'km') {
    const khmer = record[`${field}_km`]
    if (typeof khmer === 'string' && khmer.trim()) return khmer
  }

  const english = record[field]
  if (typeof english !== 'string') return ''

  // Same reasoning as blockText: seeded English content still reads in Khmer
  // when the dictionary knows the exact string.
  return language === 'km' ? translate('km', english) : english
}

/** Convenience wrapper for components that already have `language`. */
export function localizedOr(record, field, language, fallback = '') {
  return localized(record, field, language) || fallback
}

/**
 * Shared bootstrap payload (settings, nav, services, content blocks). Every
 * caller hits the same react-query cache entry, so each page costs one request.
 */
export function useContentBlocks() {
  const { data } = useQuery({
    queryKey: ['website', 'bootstrap'],
    queryFn: publicWebsiteApi.bootstrap,
    staleTime: 5 * 60_000,
  })
  return indexBlocks(data?.content_blocks)
}

/**
 * Admin-edited body text, or the developer default when empty/missing.
 *
 * In Khmer mode the `body_km` translation wins. When the editor has not filled
 * it yet, the saved English copy is still passed through the dictionary, so
 * seeded/default copy reads in Khmer instead of silently falling back.
 */
export function blockText(blocks, key, fallback, language = 'en') {
  const block = blocks?.[key]
  const raw = language === 'km' && block?.body_km ? block.body_km : block?.body
  const text = typeof raw === 'string' ? raw.trim() : ''
  const resolved = text || fallback

  return language === 'km' ? translate('km', resolved) : resolved
}

/** Admin-edited list (JSON `data`), or the default when empty/missing. */
export function blockList(blocks, key, fallback) {
  const data = blocks?.[key]?.data
  return Array.isArray(data) && data.length > 0 ? data : fallback
}

/** Admin-uploaded image URL for a block, or null. */
export function blockImage(blocks, key) {
  return blocks?.[key]?.image?.url ?? null
}

/**
 * Splits a page heading at its first space so the trailing words can render as
 * the gradient accent — mirrors what the pages did with their locked constants.
 */
export function splitHeading(heading) {
  const space = heading.indexOf(' ')
  if (space <= 0) return { lead: heading, accent: '' }
  return { lead: heading.slice(0, space), accent: heading.slice(space + 1).trim() }
}

/**
 * Language-aware `useContentBlocks` + `blockText` pair. Most pages need both,
 * so this keeps the call sites short.
 */
export function useLocalizedContent() {
  const blocks = useContentBlocks()
  const { language } = useLanguage()

  return {
    blocks,
    language,
    text: (key, fallback) => blockText(blocks, key, fallback, language),
    list: (key, fallback) => blockList(blocks, key, fallback),
    image: (key) => blockImage(blocks, key),
  }
}
