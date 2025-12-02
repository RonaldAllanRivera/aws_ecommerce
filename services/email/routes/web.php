<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email', function () {
    return view('welcome');
});

Route::get('/email/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'email',
    ]);
});
