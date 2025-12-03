<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Jobs\SendOrderCreatedMessage;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\CatalogClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutPlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_place_order_happy_path(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'TEST_CART',
            'status' => 'open',
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 2,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductById')
                ->once()
                ->with(1)
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Product',
                    'price' => '100.00',
                    'inventory' => [
                        'quantity_available' => 10,
                    ],
                ]);
        });

        $request = Request::create('/checkout/api/place-order', 'POST', [
            'cart_token' => $cart->token,
            'email' => 'customer@example.com',
            'customer_name' => 'John Doe',
            'shipping_address' => '123 Main St, City',
            'shipping_method' => 'standard',
            'tax' => 0,
            'shipping' => 0,
            'payment_token' => 'mock-token-123',
        ]);

        $response = app(CheckoutController::class)->placeOrder($request);

        $this->assertSame(201, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertSame('customer@example.com', $payload['email']);
        $this->assertSame('John Doe', $payload['customer_name']);
        $this->assertSame('123 Main St, City', $payload['shipping_address']);
        $this->assertSame('standard', $payload['shipping_method']);

        $this->assertSame('200.00', $payload['subtotal']);
        $this->assertSame('200.00', $payload['total']);

        $this->assertCount(1, $payload['items']);
        $this->assertSame('Test Product', $payload['items'][0]['product_name_snapshot']);

        $this->assertSame('checked_out', $cart->fresh()->status);

        Queue::assertPushed(SendOrderCreatedMessage::class, 1);
    }

    public function test_place_order_fails_when_price_has_changed(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'TEST_CART_PRICE',
            'status' => 'open',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 1,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductById')
                ->once()
                ->with(1)
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Product',
                    'price' => '120.00',
                    'inventory' => [
                        'quantity_available' => 10,
                    ],
                ]);
        });

        $request = Request::create('/checkout/api/place-order', 'POST', [
            'cart_token' => $cart->token,
            'email' => 'customer@example.com',
            'customer_name' => 'John Doe',
            'shipping_address' => '123 Main St, City',
            'shipping_method' => 'standard',
            'tax' => 0,
            'shipping' => 0,
        ]);

        $response = app(CheckoutController::class)->placeOrder($request);

        $this->assertSame(422, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertSame('Product price has changed. Please refresh your cart and try again.', $payload['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame('open', $cart->fresh()->status);

        Queue::assertNothingPushed();
    }

    public function test_place_order_fails_when_out_of_stock(): void
    {
        $cart = Cart::create([
            'user_id' => null,
            'token' => 'TEST_CART_STOCK',
            'status' => 'open',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'quantity' => 5,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->mock(CatalogClient::class, function ($mock) {
            $mock->shouldReceive('findProductById')
                ->once()
                ->with(1)
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Product',
                    'price' => '100.00',
                    'inventory' => [
                        'quantity_available' => 2,
                    ],
                ]);
        });

        $request = Request::create('/checkout/api/place-order', 'POST', [
            'cart_token' => $cart->token,
            'email' => 'customer@example.com',
            'customer_name' => 'John Doe',
            'shipping_address' => '123 Main St, City',
            'shipping_method' => 'standard',
            'tax' => 0,
            'shipping' => 0,
        ]);

        $response = app(CheckoutController::class)->placeOrder($request);

        $this->assertSame(422, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertSame('One or more items are out of stock or do not have enough quantity.', $payload['message']);
        $this->assertSame(0, Order::count());
        $this->assertSame('open', $cart->fresh()->status);

        Queue::assertNothingPushed();
    }
}
