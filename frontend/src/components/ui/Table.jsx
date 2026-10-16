import { cn } from '../../utils/cn.js'

export function Table({ children, className, minWidth = 'min-w-[560px]' }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-line">
      <table className={cn('w-full text-left text-sm', minWidth, className)}>
        {children}
      </table>
    </div>
  )
}

export function THead({ children, className }) {
  return (
    <thead
      className={cn(
        'bg-canvas text-xs uppercase tracking-wide text-ink-muted',
        className,
      )}
    >
      {children}
    </thead>
  )
}

export function TBody({ children, className }) {
  return <tbody className={cn('divide-y divide-line bg-surface', className)}>{children}</tbody>
}

export function Tr({ children, className, onClick }) {
  return (
    <tr
      onClick={onClick}
      className={cn(onClick ? 'cursor-pointer transition-colors hover:bg-canvas/70' : '', className)}
    >
      {children}
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

export function Td({ children, className, ...props }) {
  return (
    <td className={cn('px-4 py-3 align-middle', className)} {...props}>
      {children}
    </td>
  )
}

export default Table