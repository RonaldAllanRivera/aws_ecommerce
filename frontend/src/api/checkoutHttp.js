import axios from 'axios'

const checkoutApi = axios.create({
  baseURL:
    import.meta.env.VITE_CHECKOUT_API_BASE_URL || 'http://checkout.localhost:8080',
  withCredentials: false,
})

checkoutApi.interceptors.response.use(
  (response) => response,
  (error) => {
    const message =
      error.response?.data?.message || error.message || 'Request failed.'
    return Promise.reject(new Error(message))
  },
)

export default checkoutApi
