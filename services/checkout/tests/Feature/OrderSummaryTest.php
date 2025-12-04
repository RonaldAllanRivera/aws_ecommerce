<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CatalogClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_order_returns_summary_for_existing_order(): void
    {
        Queue::fake();

        $cart = Cart::create([
            'user_id' => null,
            'token' => 'SUMMARY_CART',
            'status' => 'open',
        ]);

        CartItem::create([
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
        $request->headers->set('Accept', 'application/json');

        $placeOrderResponse = app(CheckoutController::class)->placeOrder($request);
        $placeOrderResponse = TestResponse::fromBaseResponse($placeOrderResponse);

        $placeOrderResponse->assertStatus(201);

        $createdOrder = $placeOrderResponse->json();
        $orderNumber = $createdOrder['order_number'];

        $summaryResponse = app(CheckoutController::class)->showOrder($orderNumber);
        $summaryResponse = TestResponse::fromBaseResponse($summaryResponse);

        $summaryResponse
            ->assertOk()
            ->assertJson([
                'order_number' => $orderNumber,
                'email' => 'customer@example.com',
            ])
            ->assertJsonStructure([
                'id',
                'order_number',
                'email',
                'status',
                'subtotal',
                'tax',
                'shipping',
                'total',
                'items',
                'payment',
            ]);

        $this->assertCount(1, $summaryResponse->json('items'));
    }

    public function test_show_order_returns_404_for_unknown_order_number(): void
    {
        $response = $this->getJson('/checkout/api/orders/UNKNOWN_ORDER');

        $response->assertNotFound();
    }
}
