import api from './checkoutHttp'

export async function createOrReuseCart(cartToken) {
  const payload = {}
  if (cartToken) {
    payload.cart_token = cartToken
  }
  const response = await api.post('/checkout/api/cart', payload)
  return response.data
}

export async function getCart(cartToken) {
  const response = await api.get('/checkout/api/cart', {
    params: { cart_token: cartToken },
  })
  return response.data
}

export async function addCartItem(payload) {
  const response = await api.post('/checkout/api/cart/items', payload)
  return response.data
}

export async function updateCartItem(itemId, payload) {
  const response = await api.put(`/checkout/api/cart/items/${itemId}`, payload)
  return response.data
}

export async function deleteCartItem(itemId) {
  const response = await api.delete(`/checkout/api/cart/items/${itemId}`)
  return response.data
}

export async function placeOrder(payload) {
  const response = await api.post('/checkout/api/place-order', payload)
  return response.data
}

export async function getOrder(orderNumber) {
  const response = await api.get(`/checkout/api/orders/${orderNumber}`)
  return response.data
}
