<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'cart_token' => 'required|string',
            'email' => 'required|email',
            'tax' => 'nullable|numeric',
            'shipping' => 'nullable|numeric',
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

        $order = DB::transaction(function () use ($cart, $data, $tax, $shipping) {
            $subtotal = $cart->items->sum(function ($item) {
                return (float) $item->unit_price_snapshot * $item->quantity;
            });

            $total = $subtotal + $tax + $shipping;

            $order = Order::create([
                'order_number' => Str::upper(Str::random(12)),
                'user_id' => $cart->user_id,
                'email' => $data['email'],
                'status' => 'paid',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name_snapshot' => '',
                    'unit_price_snapshot' => $item->unit_price_snapshot,
                    'quantity' => $item->quantity,
                    'line_total' => (float) $item->unit_price_snapshot * $item->quantity,
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'mock',
                'provider_reference' => null,
                'status' => 'captured',
                'amount' => $total,
            ]);

            $cart->update([
                'status' => 'checked_out',
            ]);

            return $order;
        });

        $order->load('items', 'payment');

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
