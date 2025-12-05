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
        // Seed demo catalog data: 5 products (one out of stock) across 5 categories
        $categoryDefinitions = [
            'electronics' => 'Electronics',
            'books' => 'Books',
            'clothing' => 'Clothing',
            'home-office' => 'Home & Office',
            'accessories' => 'Accessories',
        ];

        $categoryModels = [];

        foreach ($categoryDefinitions as $slug => $name) {
            $categoryModels[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        $products = [
            [
                'sku' => 'DEMO-001',
                'name' => 'Wireless Mouse',
                'description' => 'Compact wireless mouse suitable for everyday use.',
                'price' => 19.99,
                'status' => 'active',
                'categories' => ['electronics', 'accessories'],
                'quantity_available' => 25,
            ],
            [
                'sku' => 'DEMO-002',
                'name' => 'Mechanical Keyboard',
                'description' => 'Tactile mechanical keyboard ideal for developers.',
                'price' => 59.00,
                'status' => 'active',
                'categories' => ['electronics', 'home-office'],
                'quantity_available' => 15,
            ],
            [
                'sku' => 'DEMO-003',
                'name' => 'Laravel 12 Handbook',
                'description' => 'Concise reference for building APIs and backends with Laravel 12.',
                'price' => 29.00,
                'status' => 'active',
                'categories' => ['books'],
                'quantity_available' => 40,
            ],
            [
                'sku' => 'DEMO-004',
                'name' => 'Developer Hoodie',
                'description' => 'Warm hoodie with a subtle code print.',
                'price' => 39.99,
                'status' => 'active',
                'categories' => ['clothing'],
                'quantity_available' => 20,
            ],
            [
                'sku' => 'DEMO-005',
                'name' => 'Sold Out Mug',
                'description' => 'Ceramic mug printed with "while(true){code();}" – currently out of stock.',
                'price' => 12.50,
                'status' => 'active',
                'categories' => ['home-office', 'accessories'],
                'quantity_available' => 0,
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
                    'url' => 'https://placehold.co/600x400?text=' . urlencode($product->name),
                    'alt_text' => $product->name,
                ],
            );
        }
    }
}
