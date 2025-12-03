<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/checkout', function () {
    return view('welcome');
});

Route::get('/checkout/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'checkout',
    ]);
});

Route::prefix('checkout/api')->group(function () {
    Route::post('cart', [CartController::class, 'store']);
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::put('cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{item}', [CartController::class, 'destroyItem']);

    Route::post('place-order', [CheckoutController::class, 'placeOrder']);
    Route::get('orders/{orderNumber}', [CheckoutController::class, 'showOrder']);
});
