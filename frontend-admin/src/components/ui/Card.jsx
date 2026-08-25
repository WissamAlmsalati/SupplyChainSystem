export function Card({ children, className = '' }) {
  return (
    <div
      className={`rounded-xl border border-border bg-surface p-5 shadow-sm ${className}`}
    >
      {children}
    </div>
  )
}

export function CardHeader({ children, className = '' }) {
  return <div className={`mb-4 ${className}`}>{children}</div>
}

export function CardTitle({ children, className = '' }) {
  return (
    <h3 className={`text-lg font-bold text-foreground ${className}`}>
      {children}
    </h3>
  )
}

export function CardDescription({ children, className = '' }) {
  return (
    <p className={`mt-1 text-sm text-muted ${className}`}>{children}</p>
  )
}

export function CardContent({ children, className = '' }) {
  return <div className={className}>{children}</div>
}
