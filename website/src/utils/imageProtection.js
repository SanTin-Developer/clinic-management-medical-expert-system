/**
 * Global protection for the public website's media assets.
 *
 * Visitors can still view the logo, photos and gallery images, but they
 * cannot right-click to copy/save them, nor left-click-drag them out of
 * the page. CSS alone is not enough (Firefox ignores -webkit-user-drag),
 * so the listeners below are the authoritative guard; the rules in
 * globals.css and the draggable="false" pass act as extra layers.
 */

const MEDIA_SELECTOR = 'img, picture, svg, video, canvas, audio, [style*="background-image"]'
const DRAGGABLE_MEDIA_SELECTOR = 'img, video'

let installed = false

function isMedia(target) {
  const element = target instanceof Element ? target : null
  return Boolean(element?.closest(MEDIA_SELECTOR))
}

function blockMediaContextMenu(event) {
  // Right click on the logo / any image: no "Copy image", "Save image as".
  if (isMedia(event.target)) {
    event.preventDefault()
  }
}

function blockMediaDrag(event) {
  // Left click + drag on an image would move a copy of it out of the page.
  if (isMedia(event.target)) {
    event.preventDefault()
  }
}

function disableNativeDraggables(root = document) {
  root.querySelectorAll(DRAGGABLE_MEDIA_SELECTOR).forEach((element) => {
    if (element.draggable) {
      element.draggable = false
    }
  })
}

export function installImageProtection() {
  if (installed) return
  installed = true

  document.addEventListener('contextmenu', blockMediaContextMenu)
  document.addEventListener('dragstart', blockMediaDrag)

  // Some browsers start a native drag before dragstart is cancelled, so
  // images rendered later (React pages, modals, carousels) also get
  // draggable="false" up front.
  disableNativeDraggables()

  const observer = new MutationObserver((records) => {
    records.forEach((record) => {
      record.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return
        if (node.matches(DRAGGABLE_MEDIA_SELECTOR)) {
          node.draggable = false
        }
        disableNativeDraggables(node)
      })
    })
  })
  observer.observe(document.body, { childList: true, subtree: true })
}
