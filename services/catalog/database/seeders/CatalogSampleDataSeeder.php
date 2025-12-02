<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => 'electronics',
            'Books' => 'books',
            'Clothing' => 'clothing',
        ];

        $categoryModels = [];

        foreach ($categories as $name => $slug) {
            $categoryModels[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        $products = [
            [
                'sku' => 'ELEC-001',
                'name' => 'Wireless Headphones',
                'description' => 'Comfortable over-ear wireless headphones with noise isolation.',
                'price' => 99.99,
                'status' => 'active',
                'categories' => ['electronics'],
                'quantity_available' => 50,
            ],
            [
                'sku' => 'ELEC-002',
                'name' => '4K Monitor 27"',
                'description' => '27-inch 4K IPS display ideal for development and design work.',
                'price' => 299.00,
                'status' => 'active',
                'categories' => ['electronics'],
                'quantity_available' => 20,
            ],
            [
                'sku' => 'BOOK-001',
                'name' => 'Laravel 12 in Action',
                'description' => 'Hands-on guide to building modern Laravel applications.',
                'price' => 39.00,
                'status' => 'active',
                'categories' => ['books'],
                'quantity_available' => 100,
            ],
            [
                'sku' => 'BOOK-002',
                'name' => 'Mastering Vue 3',
                'description' => 'Deep dive into the Vue 3 composition API and real-world patterns.',
                'price' => 42.50,
                'status' => 'active',
                'categories' => ['books'],
                'quantity_available' => 80,
            ],
            [
                'sku' => 'CLOTH-001',
                'name' => 'Unisex Developer T-Shirt',
                'description' => 'Soft cotton t-shirt with a subtle code-themed print.',
                'price' => 24.99,
                'status' => 'active',
                'categories' => ['clothing'],
                'quantity_available' => 200,
            ],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'status' => $data['status'],
                ],
            );

            $categoryIds = collect($data['categories'])
                ->map(fn (string $slug) => $categoryModels[$slug]->id)
                ->all();

            $product->categories()->sync($categoryIds);

            Inventory::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'quantity_available' => $data['quantity_available'],
                    'quantity_reserved' => 0,
                ],
            );

            ProductImage::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'sort_order' => 0,
                ],
                [
                    'url' => 'https://via.placeholder.com/800?text=' . urlencode($product->name),
                    'alt_text' => $product->name,
                ],
            );
        }
    }
}
