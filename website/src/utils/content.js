import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../api/website.js'

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

/** Admin-edited body text, or the developer default when empty/missing. */
export function blockText(blocks, key, fallback) {
  const raw = blocks?.[key]?.body
  const text = typeof raw === 'string' ? raw.trim() : ''
  return text || fallback
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
