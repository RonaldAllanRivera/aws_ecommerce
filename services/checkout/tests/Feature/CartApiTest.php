<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CatalogClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_new_cart_when_no_token_provided(): void
    {
        $request = Request::create('/checkout/api/cart', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->store($request);
        $response = TestResponse::fromBaseResponse($response);

        $response
            ->assertStatus(201)
            ->assertJson([
                'status' => 'open',
            ])
            ->assertJsonStructure([
                'id',
                'token',
                'status',
                'items',
                'totals' => ['subtotal'],
            ]);

        $this->assertDatabaseCount('carts', 1);

        $cart = Cart::first();
        $this->assertNotNull($cart->token);
        $this->assertSame('open', $cart->status);
    }

    public function test_store_reuses_existing_open_cart_when_token_provided(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'EXISTING_CART',
            'status' => 'open',
        ]);

        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 2,
            'unit_price_snapshot' => 50.00,
        ]);

        $request = Request::create('/checkout/api/cart', 'POST', [
            'cart_token' => 'EXISTING_CART',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->store($request);
        $response = TestResponse::fromBaseResponse($response);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $cart->id,
                'token' => 'EXISTING_CART',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data['items']);
        $this->assertEquals(100.00, $data['totals']['subtotal']);
    }

    public function test_show_returns_cart_for_valid_token(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'SHOW_CART',
            'status' => 'open',
        ]);

        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 2,
            'unit_price_snapshot' => 25.00,
        ]);

        $request = Request::create('/checkout/api/cart', 'GET', [
            'cart_token' => 'SHOW_CART',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->show($request);
        $response = TestResponse::fromBaseResponse($response);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $cart->id,
                'token' => 'SHOW_CART',
            ])
            ->assertJsonStructure([
                'id',
                'token',
                'status',
                'items',
                'totals' => ['subtotal'],
            ]);
    }

    public function test_show_returns_404_for_unknown_cart(): void
    {
        $response = $this->getJson('/checkout/api/cart?cart_token=UNKNOWN_CART');

        $response->assertNotFound();
    }

    public function test_add_item_creates_item_in_cart_using_catalog_data(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'ADD_ITEM_CART',
            'status' => 'open',
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductBySku')
                ->once()
                ->with('SKU123')
                ->andReturn([
                    'id' => 10,
                    'name' => 'Test Product',
                    'price' => '99.50',
                ]);
        });

        $request = Request::create('/checkout/api/cart/items', 'POST', [
            'cart_token' => 'ADD_ITEM_CART',
            'product_sku' => 'SKU123',
            'quantity' => 2,
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->addItem($request);
        $response = TestResponse::fromBaseResponse($response);

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(1, $data['items']);
        $this->assertSame(10, $data['items'][0]['product_id']);
        $this->assertSame(2, $data['items'][0]['quantity']);
        $this->assertSame('99.50', $data['items'][0]['unit_price']);
        $this->assertEquals(199.00, $data['totals']['subtotal']);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => 10,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_increments_existing_item_quantity(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'INC_ITEM_CART',
            'status' => 'open',
        ]);

        $item = $cart->items()->create([
            'product_id' => 10,
            'quantity' => 1,
            'unit_price_snapshot' => 50.00,
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductBySku')
                ->once()
                ->with('SKU123')
                ->andReturn([
                    'id' => 10,
                    'name' => 'Test Product',
                    'price' => '50.00',
                ]);
        });

        $request = Request::create('/checkout/api/cart/items', 'POST', [
            'cart_token' => 'INC_ITEM_CART',
            'product_sku' => 'SKU123',
            'quantity' => 2,
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->addItem($request);
        $response = TestResponse::fromBaseResponse($response);

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(1, $data['items']);
        $this->assertSame($item->id, $data['items'][0]['id']);
        $this->assertSame(3, $data['items'][0]['quantity']);
        $this->assertEquals(150.00, $data['totals']['subtotal']);
    }

    public function test_add_item_returns_422_when_catalog_client_fails(): void
    {
        Cart::create([
            'user_id' => null,
            'token' => 'FAIL_ITEM_CART',
            'status' => 'open',
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductBySku')
                ->once()
                ->andThrow(new \RuntimeException('Catalog unavailable'));
        });

        $request = Request::create('/checkout/api/cart/items', 'POST', [
            'cart_token' => 'FAIL_ITEM_CART',
            'product_sku' => 'SKU123',
            'quantity' => 1,
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->addItem($request);
        $response = TestResponse::fromBaseResponse($response);

        $response
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Unable to add item: Catalog unavailable',
            ]);
    }

    public function test_add_item_validates_required_fields(): void
    {
        $request = Request::create('/checkout/api/cart/items', 'POST', []);
        $request->headers->set('Accept', 'application/json');

        try {
            app(CartController::class)->addItem($request);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);

            $errors = $e->errors();

            $this->assertArrayHasKey('cart_token', $errors);
            $this->assertArrayHasKey('product_sku', $errors);
            $this->assertArrayHasKey('quantity', $errors);

            return;
        }

        $this->fail('Expected ValidationException was not thrown.');
    }

    public function test_update_item_changes_quantity_and_recalculates_totals(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'UPDATE_ITEM_CART',
            'status' => 'open',
        ]);

        $item = $cart->items()->create([
            'product_id' => 10,
            'quantity' => 1,
            'unit_price_snapshot' => 75.00,
        ]);

        $request = Request::create('/checkout/api/cart/items/' . $item->id, 'PUT', [
            'quantity' => 3,
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->updateItem($request, $item);
        $response = TestResponse::fromBaseResponse($response);

        $response->assertOk();

        $data = $response->json();

        $this->assertSame(3, $data['items'][0]['quantity']);
        $this->assertEquals(225.00, $data['totals']['subtotal']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_update_item_validates_minimum_quantity(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'UPDATE_ITEM_INVALID_CART',
            'status' => 'open',
        ]);

        $item = $cart->items()->create([
            'product_id' => 10,
            'quantity' => 1,
            'unit_price_snapshot' => 75.00,
        ]);

        $request = Request::create('/checkout/api/cart/items/' . $item->id, 'PUT', [
            'quantity' => 0,
        ]);
        $request->headers->set('Accept', 'application/json');

        try {
            app(CartController::class)->updateItem($request, $item);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);

            $errors = $e->errors();

            $this->assertArrayHasKey('quantity', $errors);

            return;
        }

        $this->fail('Expected ValidationException was not thrown.');
    }

    public function test_destroy_item_removes_item_and_updates_totals(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'DESTROY_ITEM_CART',
            'status' => 'open',
        ]);

        $itemToDelete = $cart->items()->create([
            'product_id' => 10,
            'quantity' => 1,
            'unit_price_snapshot' => 50.00,
        ]);

        $remainingItem = $cart->items()->create([
            'product_id' => 11,
            'quantity' => 2,
            'unit_price_snapshot' => 25.00,
        ]);

        $request = Request::create('/checkout/api/cart/items/' . $itemToDelete->id, 'DELETE');
        $request->headers->set('Accept', 'application/json');

        $response = app(CartController::class)->destroyItem($itemToDelete);
        $response = TestResponse::fromBaseResponse($response);

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(1, $data['items']);
        $this->assertSame($remainingItem->id, $data['items'][0]['id']);
        $this->assertEquals(50.00, $data['totals']['subtotal']);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $itemToDelete->id,
        ]);
    }
}
