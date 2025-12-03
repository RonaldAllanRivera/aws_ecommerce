<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Jobs\SendOrderCreatedMessage;
use App\Services\CatalogClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private CatalogClient $catalog)
    {
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'cart_token' => 'required|string',
            'email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'shipping_address' => 'required|string',
            'shipping_method' => 'nullable|string|max:100',
            'tax' => 'nullable|numeric',
            'shipping' => 'nullable|numeric',
            'payment_token' => 'nullable|string',
        ]);

        $cart = Cart::with('items')
            ->where('token', $data['cart_token'])
            ->where('status', 'open')
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty.',
            ], 422);
        }

        $tax = (float) ($data['tax'] ?? 0);
        $shipping = (float) ($data['shipping'] ?? 0);

        try {
            $order = DB::transaction(function () use ($cart, $data, $tax, $shipping) {
                $productCache = [];
                $subtotal = 0;

                foreach ($cart->items as $item) {
                    if (! isset($productCache[$item->product_id])) {
                        try {
                            $productCache[$item->product_id] = $this->catalog->findProductById($item->product_id);
                        } catch (\Throwable $e) {
                            throw new \RuntimeException('Unable to validate product against Catalog.');
                        }
                    }

                    $product = $productCache[$item->product_id];

                    $currentPrice = $product['price'] ?? null;

                    if ($currentPrice === null) {
                        throw new \RuntimeException('Product pricing unavailable for one or more items.');
                    }

                    if (bccomp((string) $currentPrice, (string) $item->unit_price_snapshot, 2) !== 0) {
                        throw new \RuntimeException('Product price has changed. Please refresh your cart and try again.');
                    }

                    $available = $product['inventory']['quantity_available'] ?? null;

                    if ($available !== null && $available < $item->quantity) {
                        throw new \RuntimeException('One or more items are out of stock or do not have enough quantity.');
                    }

                    $subtotal += (float) $item->unit_price_snapshot * $item->quantity;
                }

                $total = $subtotal + $tax + $shipping;

                $order = Order::create([
                    'order_number' => Str::upper(Str::random(12)),
                    'user_id' => $cart->user_id,
                    'email' => $data['email'],
                    'customer_name' => $data['customer_name'],
                    'shipping_address' => $data['shipping_address'],
                    'shipping_method' => $data['shipping_method'] ?? null,
                    'status' => 'paid',
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'shipping' => $shipping,
                    'total' => $total,
                ]);

                foreach ($cart->items as $item) {
                    $product = $productCache[$item->product_id];

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_name_snapshot' => $product['name'] ?? '',
                        'unit_price_snapshot' => $item->unit_price_snapshot,
                        'quantity' => $item->quantity,
                        'line_total' => (float) $item->unit_price_snapshot * $item->quantity,
                    ]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'provider' => 'mock',
                    'provider_reference' => $data['payment_token'] ?? null,
                    'status' => 'captured',
                    'amount' => $total,
                ]);

                $cart->update([
                    'status' => 'checked_out',
                ]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $order->load('items', 'payment');

        $payload = [
            'order_number' => $order->order_number,
            'email' => $order->email,
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'shipping' => $order->shipping,
            'total' => $order->total,
            'items' => $order->items->map(function (OrderItem $item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot,
                    'unit_price' => $item->unit_price_snapshot,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ];
            })->all(),
        ];

        try {
            SendOrderCreatedMessage::dispatch($payload);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch OrderCreated message', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($order, 201);
    }

    public function showOrder(string $orderNumber)
    {
        $order = Order::with('items', 'payment')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return response()->json($order);
    }
}
