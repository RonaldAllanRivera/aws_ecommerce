<script setup>
import { onMounted, reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'

const cart = useCartStore()
const router = useRouter()

const quantities = reactive({})

onMounted(async () => {
  if (!cart.cart && cart.cartToken) {
    await cart.refreshCart()
  } else if (!cart.cartToken) {
    await cart.ensureCart()
  }

  if (cart.cart?.items) {
    for (const item of cart.cart.items) {
      quantities[item.id] = item.quantity
    }
  }
})

function syncQuantities() {
  if (!cart.cart?.items) return
  for (const item of cart.cart.items) {
    if (quantities[item.id] == null) {
      quantities[item.id] = item.quantity
    }
  }
}

function updateQuantity(item) {
  const q = Number(quantities[item.id] ?? item.quantity)
  if (!Number.isFinite(q) || q < 1) return
  cart.updateItemQuantity(item.id, q)
}

function removeItem(item) {
  cart.removeItem(item.id)
}

function goToCheckout() {
  router.push({ name: 'checkout' })
}
</script>

<template>
  <div class="flex-1">
    <div class="border-b bg-white/70 backdrop-blur">
      <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
        <RouterLink to="/" class="text-lg font-semibold tracking-tight">
          AWS E-commerce
        </RouterLink>
      </div>
    </div>

    <main class="mx-auto max-w-3xl px-4 py-6" @vue:updated="syncQuantities">
      <h1 class="mb-4 text-2xl font-semibold tracking-tight text-slate-900">
        Cart
      </h1>

      <div v-if="cart.loading" class="py-8 text-sm text-slate-600">
        Loading cart...
      </div>

      <div v-else-if="cart.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ cart.error }}
      </div>

      <div v-else-if="!cart.cart || !cart.cart.items || cart.cart.items.length === 0" class="py-8 text-sm text-slate-600">
        Your cart is empty.
      </div>

      <div v-else class="grid gap-6 md:grid-cols-[2fr,1fr]">
        <section class="space-y-4">
          <article
            v-for="item in cart.cart.items"
            :key="item.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm"
          >
            <div class="flex-1">
              <p class="text-sm font-medium text-slate-900">
                Product #{{ item.product_id }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                Unit: {{ item.unit_price }} • Line: {{ item.line_total }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <input
                v-model.number="quantities[item.id]"
                type="number"
                min="1"
                class="w-16 rounded-md border border-slate-300 px-2 py-1 text-xs"
              />
              <button
                type="button"
                class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50"
                @click="updateQuantity(item)"
              >
                Update
              </button>
              <button
                type="button"
                class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                @click="removeItem(item)"
              >
                Remove
              </button>
            </div>
          </article>
        </section>

        <aside class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Summary</h2>
          <p class="flex justify-between text-sm text-slate-700">
            <span>Subtotal</span>
            <span>{{ cart.cart.totals?.subtotal ?? '0.00' }}</span>
          </p>
          <button
            type="button"
            class="mt-2 w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            :disabled="cart.loading || !cart.cart || !cart.cart.items.length"
            @click="goToCheckout"
          >
            Proceed to checkout
          </button>
        </aside>
      </div>

      <p class="mt-6 text-xs text-slate-500">
        Prices and availability are validated again during checkout.
      </p>
    </main>
  </div>
</template>
