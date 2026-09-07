import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

export default function PromoBanner() {
  const navigate = useNavigate()
  const [promos, setPromos] = useState([])

  useEffect(() => {
    client.get('/cafe/promos')
      .then((res) => {
        const items = Array.isArray(res.data) ? res.data : res.data?.data ?? []
        setPromos(items)
      })
      .catch(() => {})
  }, [])

  if (promos.length === 0) return null

  const handleClick = (link) => {
    if (!link) return
    if (/^https?:\/\//i.test(link)) {
      window.open(link, '_blank', 'noopener')
    } else if (link.startsWith('/')) {
      navigate(link)
    }
  }

  return (
    <div className="space-y-3">
      {promos.map((promo) => (
        <button
          key={promo.id}
          type="button"
          onClick={() => handleClick(promo.link)}
          className={`block w-full overflow-hidden rounded-xl border border-border bg-surface text-start shadow-sm ${promo.link ? 'cursor-pointer hover:opacity-95' : 'cursor-default'}`}
        >
          {promo.image_url && (
            <img src={promo.image_url} alt="" className="max-h-64 w-full object-cover" />
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
