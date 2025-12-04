<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useCatalogStore } from '../stores/catalog'
import { useCartStore } from '../stores/cart'

const route = useRoute()
const catalog = useCatalogStore()
const cart = useCartStore()

const product = computed(() => catalog.currentProduct)

onMounted(async () => {
  const slug = route.params.slug
  if (!product.value || product.value.slug !== slug) {
    await catalog.fetchProduct(slug)
  }
  if (!cart.cart && !cart.cartToken) {
    await cart.ensureCart()
  }
})

function isInCart() {
  if (!product.value?.id) return false
  return cart.isProductInCart?.(product.value.id)
}

async function toggleCart() {
  if (!product.value?.sku || !product.value?.id) return

  const existing = cart.findItemByProductId?.(product.value.id)

  if (existing) {
    await cart.removeItem(existing.id)
  } else {
    await cart.addItem(product.value.sku, 1)
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
      <RouterLink to="/" class="mb-4 inline-flex text-xs font-medium text-slate-600 hover:text-slate-800">
        ← Back to products
      </RouterLink>

      <div v-if="catalog.loading" class="py-8 text-sm text-slate-600">
        Loading product...
      </div>

      <div v-else-if="catalog.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ catalog.error }}
      </div>

      <section v-else-if="product" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-2 text-2xl font-semibold tracking-tight text-slate-900">
          {{ product.name }}
        </h1>
        <p class="mb-4 text-sm text-slate-600">{{ product.description }}</p>
        <p class="mb-6 text-lg font-semibold text-slate-900">
          {{ product.price }}
        </p>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium disabled:opacity-60"
          :class="
            isInCart()
              ? 'border border-red-300 bg-white text-red-700 hover:bg-red-50'
              : 'bg-slate-900 text-white hover:bg-slate-800'
          "
          :disabled="cart.loading"
          @click="toggleCart"
        >
          {{ isInCart() ? 'Remove from cart' : 'Add to cart' }}
        </button>
      </section>

      <div v-else class="py-8 text-sm text-slate-600">
        Product not found.
      </div>
    </main>
  </div>
</template>
