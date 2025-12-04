import { defineStore } from 'pinia'
import { getProducts, getProduct } from '../api/catalog'

export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    products: [],
    currentProduct: null,
    loading: false,
    error: null,
  }),
  actions: {
    async fetchProducts(params = {}) {
      this.loading = true
      this.error = null
      try {
        this.products = await getProducts(params)
      } catch (e) {
        this.error = e?.message || 'Failed to load products.'
      } finally {
        this.loading = false
      }
    },
    async fetchProduct(slug) {
      this.loading = true
      this.error = null
      try {
        this.currentProduct = await getProduct(slug)
      } catch (e) {
        this.error = e?.message || 'Failed to load product.'
      } finally {
        this.loading = false
      }
    },
  },
})
