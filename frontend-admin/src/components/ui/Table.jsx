export function Table({ children, className = '' }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
      <table className={`w-full text-right ${className}`}>{children}</table>
    </div>
  )
}

export function Thead({ children }) {
  return (
    <thead className="bg-background">
      {children}
    </thead>
  )
}

export function Tbody({ children }) {
  return <tbody className="divide-y divide-border">{children}</tbody>
}

export function Tr({ children, className = '', onClick }) {
  return (
    <tr
      onClick={onClick}
      className={`transition-colors hover:bg-stone-50/50 ${className}`}
    >
      {children}
    </tr>
  )
}

export function Th({ children, className = '' }) {
  return (
    <th
      className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted ${className}`}
    >
      {children}
    </th>
  )
}

export function Td({ children, className = '' }) {
  return (
    <td className={`px-4 py-3 text-sm text-foreground ${className}`}>
      {children}
    </td>
  )
}
