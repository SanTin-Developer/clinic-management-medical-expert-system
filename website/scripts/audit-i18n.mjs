// Dev utility: lists t('…') keys used in the website that have no Khmer
// translation yet. Run with: node scripts/audit-i18n.mjs
import fs from 'node:fs'
import path from 'node:path'

function walk(dir) {
  return fs
    .readdirSync(dir, { withFileTypes: true })
    .flatMap((entry) =>
      entry.isDirectory() ? walk(path.join(dir, entry.name)) : [path.join(dir, entry.name)],
    )
}

const sourceFiles = walk('src').filter(
  (file) => /\.(jsx|js)$/.test(file) && !file.includes(`${path.sep}i18n${path.sep}`),
)

const used = new Set()
for (const file of sourceFiles) {
  const source = fs.readFileSync(file, 'utf8')
  for (const match of source.matchAll(/\bt\(\s*'((?:[^'\\]|\\.)*)'/g)) used.add(match[1])
  for (const match of source.matchAll(/\bt\(\s*"((?:[^"\\]|\\.)*)"/g)) used.add(match[1])
}

const modules = ['common', 'home', 'about', 'services', 'team', 'gallery', 'news', 'contact', 'legal']
const translated = new Set()
for (const name of modules) {
  const source = fs.readFileSync(path.join('src', 'i18n', 'translations', `${name}.js`), 'utf8')
  for (const match of source.matchAll(
    /^\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|([A-Za-z_$][\w$]*))\s*:/gm,
  )) {
    translated.add(match[1] ?? match[2] ?? match[3])
  }
}

const missing = [...used].filter((key) => !translated.has(key)).sort()

console.log(`keys used in code: ${used.size}`)
console.log(`keys with Khmer:   ${translated.size}`)
console.log(`missing:           ${missing.length}`)
for (const key of missing) console.log(` - ${key}`)
