import { forwardRef } from 'react'

const Input = forwardRef(function Input(
  { label, error, helper, icon, className = '', ...props },
  ref,
) {
  return (
    <div className={`w-full ${className}`}>
      {label && (
        <label className="mb-1.5 block text-sm font-medium text-muted">
          {label}
        </label>
      )}
      <div className="relative">
        {icon && (
          <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-muted">
            {icon}
          </div>
        )}
        <input
          ref={ref}
          className={`w-full rounded-md border bg-surface py-2 text-foreground shadow-sm transition-colors placeholder:text-muted focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none ${
            icon ? 'pr-10 pl-3.5' : 'px-3.5'
          } ${
            error
              ? 'border-danger focus:border-danger focus:ring-danger/10'
              : 'border-border-strong'
          }`}
          {...props}
        />
      </div>
      {error && <p className="mt-1.5 text-sm text-danger">{error}</p>}
      {helper && !error && (
        <p className="mt-1.5 text-sm text-muted">{helper}</p>
      )}
    </div>
  )
})

export default Input
