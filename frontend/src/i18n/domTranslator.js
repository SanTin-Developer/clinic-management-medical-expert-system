import {
  KM_TRANSLATIONS,
  containsKhmer,
  lookupTranslation,
} from './index.js'

// Screens are being migrated to explicit `t()` calls incrementally. Until that
// migration is complete this runtime pass translates the English text that is
// still hard-coded in legacy pages, so the language switch covers the whole
// admin area instead of only the screens that already call `t()`.
const SKIP_SELECTOR = [
  'script',
  'style',
  'noscript',
  'code',
  'pre',
  'textarea',
  '[data-no-translate]',
  '[translate="no"]',
  '.notranslate',
  '[contenteditable="true"]',
].join(',')

const TRANSLATABLE_ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt']

let observer = null
let running = false
let textOriginals = new WeakMap()
let attributeOriginals = new WeakMap()
let touchedText = new Set()
let touchedAttributes = new Set()
let templates = null
let mutationCount = 0

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function buildTemplates() {
  templates = Object.entries(KM_TRANSLATIONS)
    .filter(([key]) => /\{\w+\}/.test(key))
    .map(([key, value]) => {
      const names = []
      const pattern = key
        .split(/(\{\w+\})/g)
        .map((part) => {
          const match = part.match(/^\{(\w+)\}$/)
          if (match) {
            names.push(match[1])
            return '([\\s\\S]+?)'
          }
          return escapeRegExp(part)
        })
        .join('')

      return { regex: new RegExp(`^${pattern}$`), names, value }
    })
}

/**
 * Resolves an English string, including `{count}` style templates, to Khmer.
 */
export function lookupText(source) {
  const exact = lookupTranslation(source)
  if (exact !== null) return exact

  if (!templates) buildTemplates()

  for (const entry of templates) {
    const match = entry.regex.exec(source)
    if (!match) continue

    return entry.value.replace(/\{(\w+)\}/g, (token, name) => {
      const index = entry.names.indexOf(name)
      return index === -1 ? token : (match[index + 1] ?? token)
    })
  }

  return null
}

function skipElement(element) {
  if (!element || typeof element.closest !== 'function') return false
  return Boolean(element.closest(SKIP_SELECTOR))
}

function translateTextNode(node) {
  if (!running || !node?.nodeValue) return

  const text = node.nodeValue
  if (!/[A-Za-z]/.test(text) || containsKhmer(text)) return

  const parent = node.parentElement
  if (skipElement(parent)) return

  const leading = text.match(/^\s*/)?.[0] ?? ''
  const trailing = text.match(/\s*$/)?.[0] ?? ''
  const core = text.slice(leading.length, text.length - trailing.length)
  if (!core) return

  const translated = lookupText(core)
  if (!translated || translated === core) return

  textOriginals.set(node, text)
  touchedText.add(node)
  node.nodeValue = `${leading}${translated}${trailing}`
}

function translateAttributes(element) {
  if (!running || element.nodeType !== 1) return
  if (skipElement(element)) return

  for (const attribute of TRANSLATABLE_ATTRIBUTES) {
    if (!element.hasAttribute(attribute)) continue

    const value = element.getAttribute(attribute)
    if (!value || !/[A-Za-z]/.test(value) || containsKhmer(value)) continue

    const translated = lookupText(value.trim())
    if (!translated || translated === value) continue

    let store = attributeOriginals.get(element)
    if (!store) {
      store = new Map()
      attributeOriginals.set(element, store)
    }
    if (!store.has(attribute)) store.set(attribute, value)

    element.setAttribute(attribute, translated)
    touchedAttributes.add(element)
  }
}

function translateSubtree(node) {
  if (!running || !node) return

  if (node.nodeType === 3) {
    translateTextNode(node)
    return
  }

  if (node.nodeType !== 1) return
  if (skipElement(node)) return

  translateAttributes(node)

  const walker = document.createTreeWalker(node, 4) // NodeFilter.SHOW_TEXT
  let textNode = walker.nextNode()
  while (textNode) {
    translateTextNode(textNode)
    textNode = walker.nextNode()
  }

  for (const element of node.querySelectorAll('*')) {
    translateAttributes(element)
  }
}

function handleMutations(mutations) {
  for (const mutation of mutations) {
    if (mutation.type === 'characterData') {
      translateTextNode(mutation.target)
    } else if (mutation.type === 'attributes') {
      translateAttributes(mutation.target)
    } else {
      for (const node of mutation.addedNodes) {
        translateSubtree(node)
      }
    }
  }

  // Navigation replaces large parts of the tree; drop detached nodes so the
  // bookkeeping sets do not grow for the whole session.
  mutationCount += 1
  if (mutationCount % 200 === 0 && touchedText.size > 1500) {
    for (const node of touchedText) {
      if (!node.isConnected) touchedText.delete(node)
    }
    for (const element of touchedAttributes) {
      if (!element.isConnected) touchedAttributes.delete(element)
    }
  }
}

export function startDomTranslation(root = document.body) {
  if (typeof document === 'undefined' || !root || running) return stopDomTranslation

  running = true
  textOriginals = new WeakMap()
  attributeOriginals = new WeakMap()
  touchedText = new Set()
  touchedAttributes = new Set()

  translateSubtree(root)

  observer = new MutationObserver(handleMutations)
  observer.observe(root, {
    childList: true,
    subtree: true,
    characterData: true,
    attributes: true,
    attributeFilter: TRANSLATABLE_ATTRIBUTES,
  })

  return stopDomTranslation
}

export function stopDomTranslation() {
  if (observer) {
    observer.disconnect()
    observer = null
  }

  running = false

  for (const node of touchedText) {
    if (node.isConnected && textOriginals.has(node)) {
      node.nodeValue = textOriginals.get(node)
    }
  }

  for (const element of touchedAttributes) {
    const store = attributeOriginals.get(element)
    if (!store || !element.isConnected) continue
    for (const [attribute, value] of store) {
      element.setAttribute(attribute, value)
    }
  }

  touchedText = new Set()
  touchedAttributes = new Set()
  textOriginals = new WeakMap()
  attributeOriginals = new WeakMap()
}
