<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CatalogClient
{
    public function findProductBySku(string $sku): array
    {
        $response = Http::baseUrl(config('services.catalog.base_url'))
            ->get('/catalog/api/products', [
                'sku' => $sku,
                'per_page' => 1,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to reach Catalog service.');
        }

        $payload = $response->json();
        $product = Arr::get($payload, 'data.0');

        if (! $product) {
            throw new RuntimeException('Product not found in Catalog.');
        }

        return $product;
    }

    public function findProductById(int $id): array
    {
        $response = Http::baseUrl(config('services.catalog.base_url'))
            ->get('/catalog/api/products', [
                'id' => $id,
                'per_page' => 1,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to reach Catalog service.');
        }

        $payload = $response->json();
        $product = Arr::get($payload, 'data.0');

        if (! $product) {
            throw new RuntimeException('Product not found in Catalog.');
        }

        return $product;
    }
}
