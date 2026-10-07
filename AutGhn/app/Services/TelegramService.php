<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    private function url(string $method): string
    {
        $token = config('services.telegram.bot_token');
        if (! $token) throw new RuntimeException('Chua cau hinh TELEGRAM_BOT_TOKEN.');
        return "https://api.telegram.org/bot{$token}/{$method}";
    }

    public function guiTinNhan(string|int $chatId, string $noiDung): void
    {
        Http::timeout(10)->post($this->url('sendMessage'), ['chat_id' => $chatId, 'text' => $noiDung])->throw();
    }

    public function datWebhook(string $url): void
    {
        Http::timeout(15)->post($this->url('setWebhook'), ['url' => $url, 'secret_token' => config('services.telegram.webhook_secret')])->throw();
    }
}
