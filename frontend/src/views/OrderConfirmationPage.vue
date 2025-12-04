<script setup>
import { onMounted, reactive } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { getOrder } from '../api/checkout'

const route = useRoute()

const state = reactive({
  loading: false,
  error: null,
  order: null,
})

onMounted(async () => {
  state.loading = true
  state.error = null
  try {
    state.order = await getOrder(route.params.orderNumber)
  } catch (e) {
    state.error = e?.message || 'Failed to load order.'
  } finally {
    state.loading = false
  }
})
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

    <main class="mx-auto max-w-3xl px-4 py-6">
      <h1 class="mb-4 text-2xl font-semibold tracking-tight text-slate-900">
        Order confirmation
      </h1>

      <div v-if="state.loading" class="py-8 text-sm text-slate-600">
        Loading order...
      </div>

      <div v-else-if="state.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ state.error }}
      </div>

      <section v-else-if="state.order" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-700">
          Thank you for your order. A confirmation email will be sent to
          <span class="font-medium">{{ state.order.email }}</span>.
        </p>
        <p class="text-sm text-slate-700">
          <span class="font-medium">Order #</span>
          {{ state.order.order_number }}
        </p>
        <p class="text-sm text-slate-700">
          <span class="font-medium">Status:</span>
          {{ state.order.status }}
        </p>

        <div>
          <h2 class="mb-2 text-sm font-semibold text-slate-900">Items</h2>
          <ul class="space-y-1 text-xs text-slate-700">
            <li v-for="item in state.order.items" :key="item.id" class="flex justify-between">
              <span>{{ item.product_name_snapshot ?? `Product #${item.product_id}` }} × {{ item.quantity }}</span>
              <span>{{ item.line_total }}</span>
            </li>
          </ul>
        </div>

        <div class="space-y-1 text-sm text-slate-700">
          <p class="flex justify-between">
            <span>Subtotal</span>
            <span>{{ state.order.subtotal }}</span>
          </p>
          <p class="flex justify-between">
            <span>Tax</span>
            <span>{{ state.order.tax }}</span>
          </p>
          <p class="flex justify-between">
            <span>Shipping</span>
            <span>{{ state.order.shipping }}</span>
          </p>
          <p class="flex justify-between font-semibold">
            <span>Total</span>
            <span>{{ state.order.total }}</span>
          </p>
        </div>

        <RouterLink
          to="/"
          class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
          Back to home
        </RouterLink>
      </section>

      <div v-else class="py-8 text-sm text-slate-600">
        Order not found.
      </div>
    </main>
  </div>
</template>
