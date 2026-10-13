<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        // Telegram callback chi hop le ~30s, co the timeout. Tra loi muon = user khong thay.
        // Bo qua loi HTTP 400 (query too old) vi khong anh huong den flow chinh.
        try {
            Http::timeout(5)->post($this->url('answerCallbackQuery'), $payload);
        } catch (\Throwable $e) {
            Log::warning('Khong the tra loi Telegram callback (co the da timeout)', [
                'callback_id' => $callbackId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function datWebhook(string $url): void
    {
        Http::timeout(15)->post($this->url('setWebhook'), ['url' => $url, 'secret_token' => config('services.telegram.webhook_secret')])->throw();
    }

    /**
     * Dang ky danh sach lenh (Bot Commands) de hien thi tren thanh go "/" trong Telegram.
     *
     * @param  array<int, array{command: string, description: string}>  $commands
     */
    public function dangKyLenh(array $commands): void
    {
        $payload = [
            'commands' => array_map(
                fn (array $c): array => [
                    'command' => $c['command'],
                    'description' => $c['description'],
                ],
                $commands,
            ),
            'scope' => ['type' => 'default'],
            'language_code' => 'vi',
        ];

        Http::timeout(15)->post($this->url('setMyCommands'), $payload)->throw();
    }

    /**
     * Lay thong tin bot (de lay username cho setChatMenuButton).
     */
    public function layThongTinBot(): array
    {
        $response = Http::timeout(10)->post($this->url('getMe'))->throw();
        return (array) $response->json('result', []);
    }

    /**
     * Dat nut menu gan o input cho mac dinh (ap dung cho moi user).
     * Hien tai chi ho tro type = 'default' (mo commands list).
     *
     * Theo Telegram Bot API: de thiet lap menu button mac dinh, gui request toi
     * setChatMenuButton KHONG kem chat_id (hoac chat_id=0 se bi tu choi).
     */
    public function datMenuBenCanhInputMacDinh(): void
    {
        Http::timeout(15)->post($this->url('setChatMenuButton'), [
            'menu_button' => json_encode(['type' => 'default']),
        ])->throw();
    }

    /**
     * Gui tin nhan co ban phim nut bam (Reply Keyboard, khong phai inline).
     * $keyboard la mang 2 chieu, moi phan tu la 1 hang cua cac nut.
     *
     * @param  array<int, array<int, array{text: string}>>  $keyboard
     */
    public function guiTinNhanCoBanPhim(string|int $chatId, string $noiDung, array $keyboard, bool $motLan = false): int
    {
        $replyMarkup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => $motLan,
        ];

        $response = Http::timeout(10)->post($this->url('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $noiDung,
            'reply_markup' => json_encode($replyMarkup),
        ])->throw();

        return (int) $response->json('result.message_id');
    }
}
