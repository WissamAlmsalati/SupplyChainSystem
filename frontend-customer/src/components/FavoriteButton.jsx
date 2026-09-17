import { useState } from 'react'
import { useFavorites } from '../context/FavoritesContext'

export function HeartIcon({ filled, className }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
    </svg>
  )
}

// Heart toggle for a product; stops the click from opening the product card.
export default function FavoriteButton({ productId, className = '', size = 'md' }) {
  const favorites = useFavorites()
  const [busy, setBusy] = useState(false)
  const active = favorites?.isFavorite(productId)

  const onClick = async (e) => {
    e.stopPropagation()
    e.preventDefault()
    if (busy || !favorites) return
    setBusy(true)
    try {
      await favorites.toggle(productId)
    } catch {
      // state already reverted by the context
    } finally {
      setBusy(false)
    }
  }

  const dims = size === 'lg' ? 'h-11 w-11' : 'h-9 w-9'
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      aria-label={active ? 'إزالة من المفضلة' : 'إضافة إلى المفضلة'}
      title={active ? 'إزالة من المفضلة' : 'إضافة إلى المفضلة'}
      className={`flex ${dims} items-center justify-center rounded-full bg-white/95 shadow-md transition hover:scale-105 disabled:opacity-60 ${active ? 'text-red-500' : 'text-stone-500 hover:text-red-500'} ${className}`}
      disabled={busy}
    >
      <HeartIcon filled={active} className={size === 'lg' ? 'h-6 w-6' : 'h-5 w-5'} />
    </button>
  )
}
