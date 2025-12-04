<script setup>
import { reactive } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useCartStore } from '../stores/cart'

const cart = useCartStore()
const router = useRouter()

const form = reactive({
  customer_name: '',
  email: '',
  shipping_address: '',
  shipping_method: 'standard',
})

const state = reactive({
  submitting: false,
  error: null,
  fieldErrors: {},
})

function validate() {
  state.error = null
  state.fieldErrors = {}

  if (!form.customer_name.trim()) {
    state.fieldErrors.customer_name = 'Name is required.'
  }

  if (!form.email.trim()) {
    state.fieldErrors.email = 'Email is required.'
  } else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) {
    state.fieldErrors.email = 'Enter a valid email address.'
  }

  if (!form.shipping_address.trim()) {
    state.fieldErrors.shipping_address = 'Shipping address is required.'
  }

  if (Object.keys(state.fieldErrors).length > 0) {
    state.error = 'Please fix the highlighted fields.'
    return false
  }

  return true
}

async function submit() {
  if (!validate()) {
    return
  }

  state.error = null
  state.submitting = true
  try {
    const order = await cart.checkout({
      customer_name: form.customer_name,
      email: form.email,
      shipping_address: form.shipping_address,
      shipping_method: form.shipping_method,
      tax: 0,
      shipping: 0,
      payment_token: 'mock-token',
    })

    router.push({ name: 'order-confirmation', params: { orderNumber: order.order_number } })
  } catch (e) {
    const message = e?.message || 'Failed to place order.'

    const isStockError =
      message.includes('out of stock') || message.includes('enough quantity')
    const isPriceChangeError = message.includes('price has changed')

    if (isStockError || isPriceChangeError) {
      cart.error = isStockError
        ? 'Some items are no longer available in the desired quantity. Please review your cart and try again.'
        : 'Product prices have changed since you added items to your cart. Please review your cart and confirm the new prices.'

      router.push({ name: 'cart' })
      return
    }

    if (message.includes('Cart is empty')) {
      state.error = 'Your cart is empty. Please add items before checking out.'
    } else if (message.toLowerCase().includes('payment')) {
      state.error =
        'We could not process your payment. No charges were made. Please verify your payment details and try again.'
    } else {
      state.error = message
    }
  } finally {
    state.submitting = false
  }
}
</script>

<template>
  <div class="flex-1">
    <div class="border-b bg-white/70 backdrop-blur">
      <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
        <RouterLink to="/" class="text-lg font-semibold tracking-tight">
          AWS E-commerce
        </RouterLink>
        <RouterLink
          to="/cart"
          class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
        >
          Cart
        </RouterLink>
      </div>
    </div>

    <main class="mx-auto max-w-3xl px-4 py-6">
      <h1 class="mb-4 text-2xl font-semibold tracking-tight text-slate-900">
        Checkout
      </h1>

      <div v-if="!cart.cart || !cart.cart.items || cart.cart.items.length === 0" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        Your cart is empty.
      </div>

      <section v-else class="grid gap-6 md:grid-cols-[2fr,1fr]">
        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Name</label>
            <input
              v-model="form.customer_name"
              type="text"
              :class="[
                'w-full rounded-md border px-3 py-2 text-sm focus:border-slate-500 focus:outline-none',
                state.fieldErrors.customer_name ? 'border-red-500' : 'border-slate-300',
              ]"
            />
            <p v-if="state.fieldErrors.customer_name" class="mt-1 text-xs text-red-600">
              {{ state.fieldErrors.customer_name }}
            </p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Email</label>
            <input
              v-model="form.email"
              type="email"
              :class="[
                'w-full rounded-md border px-3 py-2 text-sm focus:border-slate-500 focus:outline-none',
                state.fieldErrors.email ? 'border-red-500' : 'border-slate-300',
              ]"
            />
            <p v-if="state.fieldErrors.email" class="mt-1 text-xs text-red-600">
              {{ state.fieldErrors.email }}
            </p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Shipping address</label>
            <textarea
              v-model="form.shipping_address"
              rows="3"
              :class="[
                'w-full rounded-md border px-3 py-2 text-sm focus:border-slate-500 focus:outline-none',
                state.fieldErrors.shipping_address ? 'border-red-500' : 'border-slate-300',
              ]"
            />
            <p v-if="state.fieldErrors.shipping_address" class="mt-1 text-xs text-red-600">
              {{ state.fieldErrors.shipping_address }}
            </p>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Shipping method</label>
            <select
              v-model="form.shipping_method"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            >
              <option value="standard">Standard</option>
              <option value="express">Express</option>
            </select>
          </div>

          <div v-if="state.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
            {{ state.error }}
          </div>

          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            :disabled="state.submitting || !cart.cart.items.length"
          >
            Place order
          </button>
        </form>

        <aside class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Order summary</h2>
          <ul class="space-y-1 text-xs text-slate-700">
            <li v-for="item in cart.cart.items" :key="item.id" class="flex justify-between">
              <span>Product #{{ item.product_id }} × {{ item.quantity }}</span>
              <span>{{ item.line_total }}</span>
            </li>
          </ul>
          <p class="mt-2 flex justify-between text-sm font-medium text-slate-900">
            <span>Total</span>
            <span>{{ cart.cart.totals?.subtotal ?? '0.00' }}</span>
          </p>
        </aside>
      </section>
    </main>
  </div>
</template>
