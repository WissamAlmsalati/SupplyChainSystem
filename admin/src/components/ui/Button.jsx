import { forwardRef } from 'react'

const variants = {
  primary:
    'bg-primary text-primary-foreground border-transparent hover:bg-[#115e57] shadow-sm',
  secondary:
    'bg-surface text-foreground border-border-strong hover:bg-background',
  danger:
    'bg-danger text-danger-foreground border-transparent hover:bg-red-700 shadow-sm',
  ghost:
    'bg-transparent text-foreground border-transparent hover:bg-background',
}

const sizes = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-[15px]',
  lg: 'px-5 py-2.5 text-base',
}

const Button = forwardRef(function Button(
  { children, variant = 'secondary', size = 'md', className = '', disabled = false, ...props },
  ref,
) {
  const base =
    'inline-flex items-center justify-center gap-2 rounded-md border font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed'

  return (
    <button
      ref={ref}
      className={`${base} ${variants[variant]} ${sizes[size]} ${className}`}
      disabled={disabled}
      {...props}
    >
      {children}
    </button>
  )
})

export default Button
