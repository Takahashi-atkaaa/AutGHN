<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\TelegramService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:webhook {url?}', function (TelegramService $telegram) {
    $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/') . '/api/telegram/webhook';
    $telegram->datWebhook($url);
    $this->info("Da dang ky webhook: {$url}");
})->purpose('Dang ky webhook Telegram');
