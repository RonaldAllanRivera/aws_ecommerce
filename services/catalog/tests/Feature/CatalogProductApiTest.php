<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Product;
use Database\Seeders\CatalogSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CatalogProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSampleDataSeeder::class);
    }

    public function test_can_list_products(): void
    {
        $request = Request::create('/catalog/api/products', 'GET');

        $response = app(ProductController::class)->index($request);

        $this->assertSame(200, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('links', $payload);
        $this->assertCount(5, $payload['data']);
    }

    public function test_can_show_single_product_by_slug(): void
    {
        $product = Product::where('sku', 'ELEC-001')->firstOrFail();

        $response = app(ProductController::class)->show($product->slug);

        $this->assertSame(200, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertSame('ELEC-001', $payload['sku']);
        $this->assertSame($product->name, $payload['name']);
    }

    public function test_can_search_products_by_term(): void
    {
        $request = Request::create('/catalog/api/products', 'GET', [
            'search' => 'Laravel',
        ]);

        $response = app(ProductController::class)->index($request);

        $this->assertSame(200, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
    }
}
