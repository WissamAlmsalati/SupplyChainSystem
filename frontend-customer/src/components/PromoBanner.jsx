import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

export default function PromoBanner() {
  const navigate = useNavigate()
  const [promos, setPromos] = useState([])

  useEffect(() => {
    client.get('/customer/promos')
      .then((res) => {
        const items = Array.isArray(res.data) ? res.data : res.data?.data ?? []
        setPromos(items)
      })
      .catch(() => {})
  }, [])

  if (promos.length === 0) return null

  // The server names the destination; this maps it to a route on the web. A
  // `link` is a place outside the platform, and the two never both apply.
  const ROUTES = {
    home: () => '/',
    products: () => '/products',
    product: (id) => `/products/${id}`,
    category: (id) => `/products?category_id=${id}`,
    orders: () => '/orders',
    cart: () => '/cart',
    profile: () => '/profile',
  }

  const destinationOf = (promo) =>
    promo.deeplink_entity ? ROUTES[promo.deeplink_entity]?.(promo.deeplink_entity_id) : null

  const handleClick = (promo) => {
    if (promo.link) {
      window.open(promo.link, '_blank', 'noopener')

      return
    }
    const to = destinationOf(promo)
    if (to) navigate(to)
  }

  return (
    <div className="space-y-3">
      {promos.map((promo) => (
        <button
          key={promo.id}
          type="button"
          onClick={() => handleClick(promo)}
          className={`block w-full overflow-hidden rounded-xl border border-border bg-surface text-start shadow-sm ${promo.link || destinationOf(promo) ? 'cursor-pointer hover:opacity-95' : 'cursor-default'}`}
        >
          {promo.image && (
            <img src={promo.image} alt="" className="max-h-64 w-full object-cover" />
          )}
          {promo.show_description && promo.description && (
            <div className="p-4">
              <p className="text-sm leading-relaxed text-foreground">{promo.description}</p>
            </div>
          )}
        </button>
      ))}
    </div>
  )
}
