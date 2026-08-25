const variants = {
  default: 'bg-stone-100 text-stone-700 border-stone-200',
  primary: 'bg-primary text-primary-foreground border-transparent',
  success: 'bg-success-soft text-success border-green-200',
  warning: 'bg-warning-soft text-warning border-amber-200',
  danger: 'bg-danger-soft text-danger border-red-200',
  info: 'bg-blue-50 text-blue-700 border-blue-200',
}

export default function Badge({ children, variant = 'default', className = '' }) {
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${variants[variant]} ${className}`}
    >
      {children}
    </span>
  )
}
