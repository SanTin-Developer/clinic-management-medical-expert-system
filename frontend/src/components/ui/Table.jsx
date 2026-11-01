import { Children, Fragment, cloneElement, createContext, isValidElement, useContext } from 'react'
import { ChevronRight } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/**
 * Responsive table primitives.
 *
 * From lg upwards these render a normal data table, unchanged. Below lg each
 * `<Tr>` becomes a compact card instead:
 *
 *   [ leading ]  [ title            ]  [ badge ]  [ › ]
 *                [ secondary info   ]
 *                [ row actions      ]
 *
 * Column labels come from the matching `<Th>` automatically. Four optional
 * props on `<Td>` place a cell in the mobile card:
 *
 *   <Td leading>    left slot — date, ID, code, or icon
 *   <Td primary>    the record title
 *   <Td trailing>   right slot — status badge
 *   <Td actions>    footer row with the row actions
 *
 * Anything else becomes a secondary line under the title, prefixed with its
 * column label. `mobile={false}` keeps a low-value cell on desktop only.
 * No field or action is removed from the desktop table.
 */
const TableLabelsContext = createContext([])

/** Flatten fragments and arrays so header and body column indexes line up. */
function flattenChildren(children) {
  const out = []
  Children.forEach(children, (child) => {
    if (isValidElement(child) && child.type === Fragment) {
      out.push(...flattenChildren(child.props.children))
    } else if (child !== null && child !== undefined && typeof child !== 'boolean') {
      if (typeof child === 'string' && child.trim() === '') return
      out.push(child)
    }
  })
  return out
}

/** Read visible text from a header cell; returns null when it is sr-only. */
function headerText(node) {
  if (node === null || node === undefined || node === false || node === true) return ''
  if (typeof node === 'string' || typeof node === 'number') return String(node)
  if (Array.isArray(node)) return node.map(headerText).join('')
  if (isValidElement(node)) {
    const className = node.props?.className
    if (typeof className === 'string' && className.includes('sr-only')) return null
    return headerText(node.props?.children)
  }
  return ''
}

function isActionLabel(label) {
  return typeof label === 'string' && /^(actions?|operations?)$/i.test(label.trim())
}

/** Derive per-column labels from the table's own header row. */
function labelsFromHead(children) {
  const head = flattenChildren(children).find(
    (child) => isValidElement(child) && child.type === THead,
  )
  if (!head) return []

  const headRow = flattenChildren(head.props.children).find(
    (child) => isValidElement(child) && child.type === Tr,
  )
  if (!headRow) return []

  return flattenChildren(headRow.props.children).map((cell) => {
    if (!isValidElement(cell) || cell.type !== Th) return null
    const text = headerText(cell.props.children)
    const trimmed = typeof text === 'string' ? text.trim() : ''
    return trimmed.length > 0 ? trimmed : null
  })
}

/**
 * Keep the requested column floor from lg upwards without ever forcing the
 * table wider than its container — otherwise the last column (usually the row
 * actions) gets clipped on smaller laptops. `min()` keeps the floor only when
 * there is room for it.
 */
function desktopOnlyMinWidth(minWidth) {
  if (!minWidth) return null
  const px = minWidth.match(/(\d+)px/)
  if (!px) return minWidth.replace(/\bmin-w-/g, 'lg:min-w-')
  return `lg:[min-width:min(100%,${px[1]}px)]`
}

export function Table({ children, className, minWidth = 'min-w-[560px]' }) {
  const labels = labelsFromHead(children)

  return (
    <TableLabelsContext.Provider value={labels}>
      <div className="lg:overflow-x-auto lg:rounded-xl lg:border lg:border-line">
        <table
          className={cn(
            'w-full text-left text-sm max-lg:block',
            desktopOnlyMinWidth(minWidth),
            className,
          )}
        >
          {children}
        </table>
      </div>
    </TableLabelsContext.Provider>
  )
}

export function THead({ children, className }) {
  return (
    <thead
      className={cn(
        'bg-canvas text-xs uppercase tracking-wide text-ink-muted max-lg:hidden',
        className,
      )}
    >
      {children}
    </thead>
  )
}

export function TBody({ children, className }) {
  return (
    <tbody
      className={cn(
        'bg-surface lg:divide-y lg:divide-line max-lg:block max-lg:space-y-2.5 max-lg:bg-transparent',
        className,
      )}
    >
      {children}
    </tbody>
  )
}

/**
 * Tag every cell with its column index so `<Td>` can look up the matching
 * header. Pages that render their cells from a fragment are handled too.
 */
function withColumnIndex(children, counter) {
  return Children.map(children, (child) => {
    if (!isValidElement(child)) return child
    if (child.type === Fragment) {
      return cloneElement(child, {}, withColumnIndex(child.props.children, counter))
    }
    if (child.type === Td) {
      return cloneElement(child, { __col: counter.next() })
    }
    return child
  })
}

export function Tr({ children, className, onClick }) {
  const counter = {
    value: 0,
    next() {
      return this.value++
    },
  }
  const indexed = withColumnIndex(children, counter)
  // An empty-state row spans the whole table, so it must not become a card.
  const spanning = flattenChildren(children).some(
    (child) => isValidElement(child) && Number(child.props?.colSpan ?? 1) > 1,
  )

  return (
    <tr
      onClick={onClick}
      className={cn(
        onClick ? 'cursor-pointer transition-colors hover:bg-canvas/70' : '',
        // Mobile card: one flex line for the record header, then full-width
        // rows for secondary details and actions. Flex (not grid) so a long
        // leading value cannot starve the title.
        !spanning &&
          'max-lg:flex max-lg:flex-wrap max-lg:items-center max-lg:gap-x-2 max-lg:gap-y-0.5',
        !spanning &&
          'max-lg:rounded-xl max-lg:border max-lg:border-line max-lg:bg-surface max-lg:p-3 max-lg:shadow-[var(--shadow-card)] max-lg:hover:bg-surface',
        spanning && 'max-lg:block max-lg:p-0',
        className,
      )}
    >
      {indexed}
      {onClick ? (
        <td
          aria-hidden
          className="lg:hidden max-lg:order-4 max-lg:flex max-lg:shrink-0 max-lg:items-center max-lg:p-0"
        >
          <ChevronRight className="size-4 shrink-0 text-ink-muted/60" />
        </td>
      ) : null}
    </tr>
  )
}

export function Th({ children, className, ...props }) {
  return (
    <th className={cn('px-4 py-3 font-semibold', className)} {...props}>
      {children}
    </th>
  )
}

export function Td({
  children,
  className,
  label,
  primary,
  leading,
  trailing,
  actions,
  mobile = true,
  colSpan,
  __col,
  ...props
}) {
  const labels = useContext(TableLabelsContext)
  const resolvedLabel =
    label !== undefined ? label : __col !== undefined ? (labels[__col] ?? null) : null

  // An empty-state cell spans the table; render it as a plain centred row.
  const spanning = Number(colSpan ?? 1) > 1

  // A trailing column whose header is visually hidden (or labelled "Actions")
  // is the action column, so it becomes the card footer without every page
  // having to opt in.
  const isActions =
    !spanning &&
    (actions ||
      isActionLabel(resolvedLabel) ||
      (label === undefined &&
        resolvedLabel === null &&
        __col !== undefined &&
        __col === labels.length - 1))

  const hasLabel =
    !spanning &&
    !primary &&
    !leading &&
    !trailing &&
    !isActions &&
    resolvedLabel !== null &&
    resolvedLabel !== undefined

  return (
    <td
      colSpan={colSpan}
      className={cn(
        'px-4 py-3 align-middle',
        'max-lg:block max-lg:border-0 max-lg:p-0 max-lg:align-top',
        spanning &&
          'max-lg:basis-full max-lg:py-2 max-lg:text-center max-lg:text-xs max-lg:text-ink-muted',
        // Leading rail is capped so a long date can never starve the title.
        leading &&
          'max-lg:order-1 max-lg:flex max-lg:min-w-0 max-lg:max-w-[32%] max-lg:items-center',
        primary && 'max-lg:order-2 max-lg:min-w-0 max-lg:flex-1',
        trailing && 'max-lg:order-3 max-lg:flex max-lg:shrink-0 max-lg:items-center',
        // Secondary lines get the full card width so long values stay readable.
        hasLabel &&
          'max-lg:order-5 max-lg:basis-full max-lg:flex max-lg:items-baseline max-lg:gap-1.5 max-lg:py-0.5',
        isActions &&
          'max-lg:order-6 max-lg:mt-2 max-lg:basis-full max-lg:flex max-lg:justify-end max-lg:border-t max-lg:border-line max-lg:pt-2',
        className,
        // Keep this last so tailwind-merge does not drop it in favour of the
        // display class added by the role above.
        !mobile && 'max-lg:hidden',
      )}
      {...props}
    >
      {hasLabel ? (
        <span className="lg:hidden max-lg:inline max-lg:shrink-0 max-lg:text-[10px] max-lg:font-semibold max-lg:uppercase max-lg:tracking-wide max-lg:text-ink-muted/70">
          {resolvedLabel}
        </span>
      ) : null}
      <span
        className={cn(
          'max-lg:min-w-0',
          leading && 'max-lg:block max-lg:truncate max-lg:text-xs',
          hasLabel && 'max-lg:flex-1 max-lg:truncate max-lg:text-xs max-lg:text-ink-muted',
          primary && 'max-lg:block max-lg:truncate max-lg:text-sm max-lg:font-semibold max-lg:text-ink',
        )}
      >
        {children}
      </span>
    </td>
  )
}

export default Table
