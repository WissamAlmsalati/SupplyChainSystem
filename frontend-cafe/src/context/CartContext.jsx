import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import client from '../api/client'

const CartContext = createContext(null)

export function CartProvider({ children }) {
  const [cart, setCart] = useState(null)
  const [loading, setLoading] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const res = await client.get('/cafe/cart')
      setCart(res.data?.data ?? null)
    } catch {
      setCart(null)
    }
  }, [])

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) refresh()
  }, [refresh])

  const addItem = async (productVariantId, quantity) => {
    const res = await client.post('/cafe/cart/items', {
      product_variant_id: productVariantId,
      quantity,
    })
    setCart(res.data?.data?.cart ?? res.data?.cart ?? null)
    return res.data
  }

  const updateItem = async (itemId, quantity) => {
    const res = await client.put(`/cafe/cart/items/${itemId}`, { quantity })
    setCart(res.data?.data ?? res.data)
    return res.data
  }

  const removeItem = async (itemId) => {
    const res = await client.delete(`/cafe/cart/items/${itemId}`)
    setCart(res.data?.data ?? res.data)
    return res.data
  }

  const clearCart = async () => {
    await client.delete('/cafe/cart')
    setCart((prev) => (prev ? { ...prev, items: [], subtotal: 0 } : prev))
  }

  const checkout = async (addressId, paymentMethod = 'cash') => {
    const res = await client.post('/cafe/cart/checkout', { address_id: addressId, payment_method: paymentMethod })
    await refresh()
    return res.data
  }

  const itemCount = cart?.items?.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0) || 0

  return (
    <CartContext.Provider
      value={{
        cart,
        loading,
        itemCount,
        refresh,
        addItem,
        updateItem,
        removeItem,
        clearCart,
        checkout,
      }}
    >
      {children}
    </CartContext.Provider>
  )
}

export function useCart() {
  return useContext(CartContext)
}
