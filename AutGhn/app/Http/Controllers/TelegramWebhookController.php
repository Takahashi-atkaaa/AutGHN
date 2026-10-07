<?php

namespace App\Http\Controllers;

use App\Models\TinNhanTelegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramService;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['success' => false, 'message' => 'Khong hop le'], 403);
        }

        $data = $request->json()->all();
        $tinNhan = $data['message'] ?? $data['edited_message'] ?? null;
        if (! is_array($tinNhan) || ! isset($tinNhan['message_id'], $tinNhan['chat']['id'])) {
            return response()->json(['success' => true, 'message' => 'Bo qua su kien khong phai tin nhan']);
        }

        $maTinNhan = $tinNhan['chat']['id'].'_'.$tinNhan['message_id'];
        $noiDung = (string) ($tinNhan['text'] ?? $tinNhan['caption'] ?? '');
        $nguoiGui = $tinNhan['from']['username'] ?? $tinNhan['from']['first_name'] ?? null;

        $banGhi = TinNhanTelegram::firstOrCreate(
            ['ma_tin_nhan' => $maTinNhan],
            [
                'ma_kenh' => (string) $tinNhan['chat']['id'],
                'ten_nguoi_gui' => $nguoiGui,
                'noi_dung_goc' => $noiDung,
                'trang_thai' => 'moi',
            ],
        );

        Log::info('Telegram webhook da tiep nhan', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'moi' => $banGhi->wasRecentlyCreated,
        ]);

        if ($banGhi->wasRecentlyCreated && $noiDung !== '') {
            try {
                $telegram->guiTinNhan($tinNhan['chat']['id'], 'Da nhan tin nhan. Dang chuan bi doc don.');
            } catch (\Throwable $exception) {
                Log::error('Khong the phan hoi Telegram', ['loi' => $exception->getMessage()]);
            }
        }

        return response()->json(['success' => true]);
    }
}
