import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { HeartIcon } from './FavoriteButton'
import { usePremiumFeatureActive } from '../hooks/usePremiumFeatureActive'
import { useState } from 'react'

function MenuIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  )
}

function SearchIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" />
    </svg>
  )
}

function CartIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z" />
    </svg>
  )
}

function UserIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
    </svg>
  )
}

function LogoutIcon({ className }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
    </svg>
  )
}

export default function Header({ onMenuClick }) {
  const { user, logout } = useAuth()
  const branchesFeature = usePremiumFeatureActive('cafe_branches')
  const { itemCount } = useCart()
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [profileOpen, setProfileOpen] = useState(false)

  const handleSearch = (e) => {
    e.preventDefault()
    if (search.trim()) {
      navigate(`/products?search=${encodeURIComponent(search.trim())}`)
    }
  }

  return (
    <header className="sticky top-0 z-30 border-b border-border bg-surface shadow-sm">
      <div className="flex items-center gap-4 px-4 py-3 lg:px-8">
        <button
          type="button"
          onClick={onMenuClick}
          className="rounded-lg p-2 text-muted hover:bg-background lg:hidden"
        >
          <MenuIcon className="h-5 w-5" />
        </button>

        <Link to="/" className="flex items-center gap-2">
          <img src={`${import.meta.env.BASE_URL}logo.svg`} alt="الساحل" className="h-10 w-auto" />
          <span className="hidden text-lg font-bold text-foreground sm:block">الساحل</span>
        </Link>

        <form onSubmit={handleSearch} className="mx-4 hidden flex-1 max-w-xl sm:block">
          <div className="relative">
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="ابحث عن منتج..."
              className="w-full rounded-lg border border-border-strong bg-background py-2 pe-10 ps-4 text-sm text-foreground outline-none focus:border-primary"
            />
            <button
              type="submit"
              className="absolute inset-y-0 end-0 flex items-center px-3 text-muted hover:text-foreground"
            >
              <SearchIcon className="h-4 w-4" />
            </button>
          </div>
        </form>

        <div className="ms-auto flex items-center gap-2 sm:gap-4">
          {user?.user_type?.name !== 'delegate' && (
            <Link to="/favorites" className="rounded-lg p-2 text-muted hover:bg-background hover:text-red-500" aria-label="المفضلة" title="المفضلة">
              <HeartIcon className="h-5 w-5" />
            </Link>
          )}
          <Link
            to="/cart"
            className="relative rounded-lg p-2 text-muted hover:bg-background hover:text-foreground"
          >
            <CartIcon className="h-5 w-5" />
            {itemCount > 0 && (
              <span className="absolute -top-1 -end-1 flex h-5 w-5 items-center justify-center rounded-full bg-danger text-xs font-bold text-white">
                {itemCount}
              </span>
            )}
          </Link>

          <div className="relative">
            <button
              onClick={() => setProfileOpen((v) => !v)}
              className="flex items-center gap-2 rounded-lg p-2 text-muted hover:bg-background hover:text-foreground"
            >
              <UserIcon className="h-5 w-5" />
              <span className="hidden max-w-[8rem] truncate text-sm font-medium sm:block">
                {user?.name}
              </span>
            </button>

            {profileOpen && (
              <div className="absolute end-0 top-full z-40 mt-2 w-48 rounded-lg border border-border bg-surface p-1 shadow-lg">
                <Link
                  to="/profile"
                  onClick={() => setProfileOpen(false)}
                  className="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-background"
                >
                  الملف الشخصي
                </Link>
                <Link
                  to="/orders"
                  onClick={() => setProfileOpen(false)}
                  className="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-background"
                >
                  طلباتي
                </Link>
                {branchesFeature && (
                  <Link
                    to="/addresses"
                    onClick={() => setProfileOpen(false)}
                    className="block rounded-md px-3 py-2 text-sm text-foreground hover:bg-background"
                  >
                    عناويني
                  </Link>
                )}
                <hr className="my-1 border-border" />
                <button
                  onClick={() => {
                    setProfileOpen(false)
                    logout()
                  }}
                  className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-danger hover:bg-danger-soft"
                >
                  <LogoutIcon className="h-4 w-4" />
                  تسجيل الخروج
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      <form onSubmit={handleSearch} className="border-t border-border px-4 pb-3 sm:hidden">
        <div className="relative mt-2">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ابحث عن منتج..."
            className="w-full rounded-lg border border-border-strong bg-background py-2 pe-10 ps-4 text-sm text-foreground outline-none focus:border-primary"
          />
          <button
            type="submit"
            className="absolute inset-y-0 end-0 flex items-center px-3 text-muted hover:text-foreground"
          >
            <SearchIcon className="h-4 w-4" />
          </button>
        </div>
      </form>
    </header>
  )
}
