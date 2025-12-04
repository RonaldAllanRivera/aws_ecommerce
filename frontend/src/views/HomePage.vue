<script setup>
import { onMounted } from 'vue'
import { useCatalogStore } from '../stores/catalog'
import { useCartStore } from '../stores/cart'
import { RouterLink } from 'vue-router'

const catalog = useCatalogStore()
const cart = useCartStore()

onMounted(() => {
  if (!catalog.products.length) {
    catalog.fetchProducts()
  }
  if (!cart.cart && !cart.cartToken) {
    cart.ensureCart()
  }
})

function isInCart(product) {
  if (!product || !product.id) return false
  return cart.isProductInCart?.(product.id)
}

async function toggleCart(product) {
  if (!product?.sku || !product?.id) return

  const existing = cart.findItemByProductId?.(product.id)

  if (existing) {
    await cart.removeItem(existing.id)
  } else {
    await cart.addItem(product.sku, 1)
  }
}
</script>

<template>
  <div class="flex-1">
    <div class="border-b bg-white/70 backdrop-blur">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
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

    <main class="mx-auto max-w-5xl px-4 py-6">
      <h1 class="mb-4 text-2xl font-semibold tracking-tight text-slate-900">
        Products
      </h1>

      <div v-if="catalog.loading" class="py-8 text-sm text-slate-600">
        Loading products...
      </div>

      <div v-else-if="catalog.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ catalog.error }}
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="product in catalog.products"
          :key="product.id"
          class="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
        >
          <RouterLink
            :to="{ name: 'product-detail', params: { slug: product.slug } }"
            class="block aspect-[4/3] bg-slate-100"
          >
            <img
              v-if="product.images && product.images.length"
              :src="product.images[0].url"
              :alt="product.images[0].alt_text || product.name"
              class="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full w-full items-center justify-center text-xs font-medium text-slate-400"
            >
              No image
            </div>
          </RouterLink>

          <div class="flex flex-1 flex-col p-4">
            <RouterLink
              :to="{ name: 'product-detail', params: { slug: product.slug } }"
              class="mb-1 line-clamp-2 text-sm font-medium text-slate-900 hover:text-slate-700"
            >
              {{ product.name }}
            </RouterLink>
            <p class="mb-4 text-sm text-slate-600">
              {{ product.price }}
            </p>
          <div class="mt-auto flex justify-between gap-2">
            <RouterLink
              :to="{ name: 'product-detail', params: { slug: product.slug } }"
              class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
            >
              View
            </RouterLink>
            <button
              type="button"
              class="inline-flex flex-1 items-center justify-center rounded-md px-3 py-1 text-xs font-medium disabled:opacity-60"
              :class="
                isInCart(product)
                  ? 'border border-red-300 bg-white text-red-700 hover:bg-red-50'
                  : 'bg-slate-900 text-white hover:bg-slate-800'
              "
              :disabled="cart.loading"
              @click="toggleCart(product)"
            >
              {{ isInCart(product) ? 'Remove from cart' : 'Add to cart' }}
            </button>
          </div>
          </div>
        </article>
      </div>
    </main>
  </div>
</template>
