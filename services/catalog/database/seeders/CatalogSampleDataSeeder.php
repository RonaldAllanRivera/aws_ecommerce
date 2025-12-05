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
        // Minimal seed for EC2: 1 in-stock product, 1 out-of-stock product
        $categoryModels = [
            'demo' => Category::updateOrCreate(
                ['slug' => 'demo'],
                ['name' => 'Demo']
            ),
        ];

        $products = [
            [
                'sku' => 'DEMO-001',
                'name' => 'Demo Widget',
                'description' => 'In-stock demo product.',
                'price' => 9.99,
                'status' => 'active',
                'categories' => ['demo'],
                'quantity_available' => 10,
            ],
            [
                'sku' => 'DEMO-002',
                'name' => 'Sold Out Widget',
                'description' => 'Out-of-stock demo product.',
                'price' => 7.50,
                'status' => 'active',
                'categories' => ['demo'],
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
