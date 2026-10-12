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

    public function guiTinNhanCoPhim(string|int $chatId, string $noiDung, array $phim): int
    {
        $response = Http::timeout(10)->post($this->url('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $noiDung,
            'reply_markup' => json_encode([
                'inline_keyboard' => $phim,
            ]),
        ])->throw();

        return (int) $response->json('result.message_id');
    }

    public function traLoiCallback(string $callbackId, string $text = '', bool $hienThiAlert = false): void
    {
        $payload = [
            'callback_query_id' => $callbackId,
        ];

        if ($text !== '') {
            $payload['text'] = $text;
            if ($hienThiAlert) {
                $payload['show_alert'] = true;
            }
        }

        Http::timeout(5)->post($this->url('answerCallbackQuery'), $payload)->throw();
    }

    public function datWebhook(string $url): void
    {
        Http::timeout(15)->post($this->url('setWebhook'), ['url' => $url, 'secret_token' => config('services.telegram.webhook_secret')])->throw();
    }
}
