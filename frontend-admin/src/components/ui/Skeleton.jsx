export function Skeleton({ className = '', circle = false }) {
  return (
    <div
      className={`animate-pulse bg-border ${circle ? 'rounded-full' : 'rounded-md'} ${className}`}
    />
  )
}

export function SkeletonText({ lines = 1, className = '' }) {
  return (
    <div className={`space-y-2 ${className}`}>
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton key={i} className="h-4 w-full last:w-4/5" />
      ))}
    </div>
  )
}

export function SkeletonCard({ className = '' }) {
  return (
    <div className={`rounded-xl border border-border bg-surface p-5 shadow-sm ${className}`}>
      <Skeleton className="mb-4 h-5 w-1/3" />
      <Skeleton className="h-8 w-2/3" />
    </div>
  )
}

export function PageSkeleton({ titleWidth = 'w-48' }) {
  return (
    <div className="space-y-6">
      <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Skeleton className={`h-8 ${titleWidth}`} />
          <Skeleton className="mt-2 h-4 w-64" />
        </div>
        <Skeleton className="h-10 w-28" />
      </header>
      <SkeletonCard />
      <SkeletonCard />
    </div>
  )
}

export function SkeletonTable({ columns = 4, rows = 5 }) {
  return (
    <div className="space-y-3">
      <div className="overflow-hidden rounded-lg border border-border">
        <table className="w-full">
          <thead className="bg-background">
            <tr>
              {Array.from({ length: columns }).map((_, i) => (
                <th key={i} className="px-4 py-3 text-start">
                  <Skeleton className="h-4 w-20" />
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {Array.from({ length: rows }).map((_, rowIdx) => (
              <tr key={rowIdx}>
                {Array.from({ length: columns }).map((__, colIdx) => (
                  <td key={colIdx} className="px-4 py-3">
                    <Skeleton className="h-4 w-full" style={{ maxWidth: colIdx === 0 ? '60%' : '80%' }} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
