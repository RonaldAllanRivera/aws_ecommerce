import { createRouter, createWebHistory } from 'vue-router'

import HomePage from './views/HomePage.vue'
import ProductDetailPage from './views/ProductDetailPage.vue'
import CartPage from './views/CartPage.vue'
import CheckoutPage from './views/CheckoutPage.vue'
import OrderConfirmationPage from './views/OrderConfirmationPage.vue'

const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/products/:slug', name: 'product-detail', component: ProductDetailPage, props: true },
  { path: '/cart', name: 'cart', component: CartPage },
  { path: '/checkout', name: 'checkout', component: CheckoutPage },
  { path: '/order/:orderNumber', name: 'order-confirmation', component: OrderConfirmationPage, props: true },
]

export function createAppRouter() {
  return createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
      return { top: 0 }
    },
  })
}
