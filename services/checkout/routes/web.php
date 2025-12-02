<?php

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
