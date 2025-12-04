import api from './http'

export async function getProducts(params = {}) {
  const response = await api.get('/catalog/api/products', { params })
  return response.data.data || response.data
}

export async function getProduct(slug) {
  const response = await api.get(`/catalog/api/products/${slug}`)
  return response.data
}
