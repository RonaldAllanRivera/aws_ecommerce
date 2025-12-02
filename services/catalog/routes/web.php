<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/catalog', function () {
    return view('welcome');
});

Route::get('/catalog/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'catalog',
    ]);
});
