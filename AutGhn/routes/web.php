<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ShopGhnController;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('shop-ghn', ShopGhnController::class);
