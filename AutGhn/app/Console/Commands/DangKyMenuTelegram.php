<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class DangKyMenuTelegram extends Command
{
    protected $signature = 'telegram:dang-ky-menu';

    protected $description = 'Dang ky commands va menu cho bot Telegram.';

    public function handle(TelegramService $telegram): int
    {
        $commands = [
            ['command' => 'start', 'description' => 'Bat dau su dung bot'],
            ['command' => 'help', 'description' => 'Xem huong dan su dung'],
            ['command' => 'cancel', 'description' => 'Huy thao tac dang thuc hien'],
        ];

        try {
            $telegram->dangKyLenh($commands);
            $this->info('Da dang ky ' . count($commands) . ' lenh cho bot Telegram.');
        } catch (\Throwable $e) {
            $this->error('Loi dang ky lenh: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $telegram->datMenuBenCanhInputMacDinh();
            $this->info('Da dat nut menu ben canh input (mac dinh).');
        } catch (\Throwable $e) {
            $this->warn('Khong the dat nut menu ben canh input: ' . $e->getMessage());
        }

        try {
            $bot = $telegram->layThongTinBot();
            if (! empty($bot['username'])) {
                $this->info('Bot username: @' . $bot['username']);
            }
        } catch (\Throwable $e) {
            $this->warn('Khong the lay thong tin bot: ' . $e->getMessage());
        }

        $this->line('');
        $this->line('Sau khi dang ky, khi user go "/" trong Telegram se thay cac lenh tren.');

        return self::SUCCESS;
    }
}
