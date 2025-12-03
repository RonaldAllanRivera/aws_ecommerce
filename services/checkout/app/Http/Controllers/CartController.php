<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function store(Request $request)
    {
        $token = $request->input('cart_token') ?? $request->query('cart_token');

        $baseQuery = Cart::query()->where('status', 'open');

        if ($token) {
            $existing = (clone $baseQuery)->where('token', $token)->first();

            if ($existing) {
                $existing->load('items');

                return response()->json($this->transformCart($existing));
            }
        }

        if ($request->user()) {
            $existing = (clone $baseQuery)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existing) {
                $existing->load('items');

                return response()->json($this->transformCart($existing));
            }
        }

        $cart = Cart::create([
            'user_id' => $request->user()?->id,
            'token' => (string) Str::uuid(),
            'status' => 'open',
        ]);

        $cart->load('items');

        return response()->json($this->transformCart($cart), 201);
    }

    public function show(Request $request)
    {
        $token = $request->query('cart_token');

        $cart = Cart::with('items')
            ->where('token', $token)
            ->where('status', 'open')
            ->firstOrFail();

        return response()->json($this->transformCart($cart));
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'cart_token' => 'required|string',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric',
        ]);

        $cart = Cart::where('token', $data['cart_token'])
            ->where('status', 'open')
            ->firstOrFail();

        $item = $cart->items()
            ->where('product_id', $data['product_id'])
            ->first();

        if ($item) {
            $item->quantity += $data['quantity'];
            $item->unit_price_snapshot = $data['unit_price'];
            $item->save();
        } else {
            $cart->items()->create([
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'unit_price_snapshot' => $data['unit_price'],
            ]);
        }

        $cart->load('items');

        return response()->json($this->transformCart($cart));
    }

    public function updateItem(Request $request, CartItem $item)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item->update([
            'quantity' => $data['quantity'],
        ]);

        $item->cart->load('items');

        return response()->json($this->transformCart($item->cart));
    }

    public function destroyItem(CartItem $item)
    {
        $cart = $item->cart;

        $item->delete();

        $cart->load('items');

        return response()->json($this->transformCart($cart));
    }

    protected function transformCart(Cart $cart): array
    {
        $items = $cart->items->map(function (CartItem $item) {
            $lineTotal = (float) $item->unit_price_snapshot * $item->quantity;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price_snapshot,
                'line_total' => $lineTotal,
            ];
        });

        $subtotal = $items->sum('line_total');

        return [
            'id' => $cart->id,
            'token' => $cart->token,
            'status' => $cart->status,
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
            ],
        ];
    }
}
