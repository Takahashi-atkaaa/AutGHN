<?php

use App\Http\Controllers\GhnWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
Route::post('/ghn/webhook', [GhnWebhookController::class, 'handle']);
