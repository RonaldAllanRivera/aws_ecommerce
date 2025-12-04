import { defineStore } from 'pinia'
import {
  createOrReuseCart,
  getCart,
  addCartItem,
  updateCartItem,
  deleteCartItem,
  placeOrder,
} from '../api/checkout'

const CART_TOKEN_KEY = 'checkout_cart_token'

export const useCartStore = defineStore('cart', {
  state: () => ({
    cartToken: localStorage.getItem(CART_TOKEN_KEY) || null,
    cart: null,
    loading: false,
    error: null,
  }),
  getters: {
    items(state) {
      return state.cart?.items || []
    },
    isProductInCart: (state) => (productId) => {
      return (state.cart?.items || []).some(
        (item) => item.product_id === productId,
      )
    },
    findItemByProductId: (state) => (productId) => {
      return (
        (state.cart?.items || []).find(
          (item) => item.product_id === productId,
        ) || null
      )
    },
  },
  actions: {
    setCartToken(token) {
      this.cartToken = token
      if (token) {
        localStorage.setItem(CART_TOKEN_KEY, token)
      } else {
        localStorage.removeItem(CART_TOKEN_KEY)
      }
    },
    async ensureCart() {
      this.loading = true
      this.error = null
      try {
        const response = await createOrReuseCart(this.cartToken)
        this.setCartToken(response.token)
        this.cart = response
      } catch (e) {
        this.error = e?.message || 'Failed to ensure cart.'
      } finally {
        this.loading = false
      }
    },
    async refreshCart() {
      if (!this.cartToken) return
      this.loading = true
      this.error = null
      try {
        this.cart = await getCart(this.cartToken)
      } catch (e) {
        this.error = e?.message || 'Failed to load cart.'
      } finally {
        this.loading = false
      }
    },
    async addItem(productSku, quantity = 1) {
      this.loading = true
      this.error = null
      try {
        if (!this.cartToken) {
          await this.ensureCart()
        }
        this.cart = await addCartItem({
          cart_token: this.cartToken,
          product_sku: productSku,
          quantity,
        })
      } catch (e) {
        this.error = e?.message || 'Unable to add item to cart.'
      } finally {
        this.loading = false
      }
    },
    async updateItemQuantity(itemId, quantity) {
      this.loading = true
      this.error = null
      try {
        this.cart = await updateCartItem(itemId, { quantity })
      } catch (e) {
        this.error = e?.message || 'Unable to update cart item.'
      } finally {
        this.loading = false
      }
    },
    async removeItem(itemId) {
      this.loading = true
      this.error = null
      try {
        this.cart = await deleteCartItem(itemId)
      } catch (e) {
        this.error = e?.message || 'Unable to remove cart item.'
      } finally {
        this.loading = false
      }
    },
    async checkout(form) {
      if (!this.cartToken) {
        throw new Error('Cart is empty.')
      }
      this.loading = true
      this.error = null
      try {
        const order = await placeOrder({
          cart_token: this.cartToken,
          ...form,
        })

        this.cart = null
        this.setCartToken(null)

        return order
      } catch (e) {
        this.error = e?.message || 'Failed to place order.'
        throw e
      } finally {
        this.loading = false
      }
    },
  },
})
