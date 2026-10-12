<?php

namespace App\Http\Controllers;

use App\Models\VanDon;
use App\Models\LichSuTrangThaiVanDon;
use App\Models\WebhookGhn;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GhnWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): \Illuminate\Http\Response
    {
        $secret = (string) config('services.ghn.webhook_secret');
        $providedSecret = (string) $request->header('X-GHN-Webhook-Secret');
        if ($secret === '' || $providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            Log::warning('GHN webhook bi tu choi do secret khong hop le');
            return response('unauthorized', 401);
        }

        $data = $request->json()->all();

        Log::info('GHN webhook nhan duoc', [
            'order_code' => $data['order_code'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        // GHN có thể gửi nhiều trạng thái cho cùng một order_code.
        // Chỉ cùng một payload mới là webhook trùng; không dùng order_code làm khóa sự kiện.
        $eventKey = (string) ($data['event_id'] ?? $data['id'] ?? hash(
            'sha256',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));

        try {
            // Luu webhook goc
            $webhook = WebhookGhn::create([
                'ma_su_kien' => $eventKey,
                'ma_van_don' => $data['order_code'] ?? null,
                'du_lieu_goc' => $data,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // MySQL và SQLite dùng exception khác nhau; kiểm tra mã SQLSTATE chuẩn.
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                Log::info('GHN webhook trung, bo qua', ['event_key' => $eventKey]);
                return response('ok');
            }
            throw $e;
        }

        // Chi xu ly neu la ban ghi moi
        if ($webhook->wasRecentlyCreated) {
            $this->xuLyWebhook($data, $telegram);
        }

        return response('ok');
    }

    private function xuLyWebhook(array $data, TelegramService $telegram): void
    {
        $orderCode = $data['order_code'] ?? null;
        if (!$orderCode) {
            Log::warning('GHN webhook khong co order_code');
            return;
        }

        $vanDon = VanDon::where('ma_van_don', $orderCode)->first();
        if (!$vanDon) {
            Log::warning('GHN webhook khong tim thay van don', ['order_code' => $orderCode]);
            return;
        }

        // Map trang thai GHN -> trang thai noi bo
        $trangThai = $this->mapTrangThai($data['status'] ?? '');

        // Cap nhat trang thai van_don
        $vanDon->update(['trang_thai' => $trangThai]);

        // Luu lich su trang thai
        LichSuTrangThaiVanDon::create([
            'van_don_id' => $vanDon->id,
            'ma_trang_thai_ghn' => $data['status'] ?? null,
            'trang_thai' => $trangThai,
            'ghi_chu' => $data['message'] ?? null,
            'du_lieu_ghn' => $data,
            'thoi_gian_ghn' => $data['updated_at'] ?? now(),
        ]);

        // Gui thong bao Telegram
        $this->guiThongBaoTelegram($vanDon, $trangThai, $telegram);

        // Danh dau da xu ly
        WebhookGhn::where('ma_van_don', $orderCode)
            ->where('da_xu_ly', false)
            ->update(['da_xu_ly' => true, 'thoi_gian_xu_ly' => now()]);

        Log::info('GHN webhook da xu ly', [
            'order_code' => $orderCode,
            'trang_thai_cu' => $vanDon->getOriginal('trang_thai'),
            'trang_thai_moi' => $trangThai,
        ]);
    }

    private function mapTrangThai(string $status): string
    {
        // GHN status codes -> noi bo status
        $map = [
            'order_placed' => 'da_dat',
            'pickup' => 'dang_lay_hang',
            'picking' => 'dang_lay_hang',
            'picked' => 'da_lay_hang',
            'store' => 'dang_luu_kho',
            'storing' => 'dang_luu_kho',
            'transport' => 'dang_van_chuyen',
            'transporting' => 'dang_van_chuyen',
            'delivery' => 'dang_giao',
            'delivering' => 'dang_giao',
            'delivered' => 'da_giao',
            'return' => 'tra_ve',
            'returning' => 'dang_tra_ve',
            'returned' => 'da_tra_ve',
            'cancelled' => 'da_huy',
            'cancel' => 'da_huy',
        ];

        return $map[$status] ?? 'khong_ro';
    }

    private function layTextTrangThai(string $trangThai): string
    {
        $textMap = [
            'da_dat' => 'Don da dat',
            'dang_lay_hang' => 'Dang lay hang',
            'da_lay_hang' => 'Da lay hang',
            'dang_luu_kho' => 'Dang luu kho',
            'dang_van_chuyen' => 'Dang van chuyen',
            'dang_giao' => 'Dang giao hang',
            'da_giao' => 'Da giao thanh cong',
            'tra_ve' => 'Dang tra ve',
            'dang_tra_ve' => 'Dang tra ve',
            'da_tra_ve' => 'Da tra ve nguoi gui',
            'da_huy' => 'Da huy',
            'khong_ro' => 'Khong ro trang thai',
        ];

        return $textMap[$trangThai] ?? $trangThai;
    }

    private function guiThongBaoTelegram(VanDon $vanDon, string $trangThai, TelegramService $telegram): void
    {
        $donHang = $vanDon->donHang;
        if (!$donHang) {
            Log::warning('GHN webhook khong tim thay don hang', ['van_don_id' => $vanDon->id]);
            return;
        }

        $tinNhan = $donHang->tinNhanTelegram;
        if (!$tinNhan) {
            Log::warning('GHN webhook khong tim thay tin nhan telegram', ['don_hang_id' => $donHang->id]);
            return;
        }

        $trangThaiText = $this->layTextTrangThai($trangThai);
        $thongBao = "CAP NHAT TRANG THAI\n\n";
        $thongBao .= "Ma don: {$donHang->ma_don_noi_bo}\n";
        $thongBao .= "Ma van don: {$vanDon->ma_van_don}\n";
        $thongBao .= "Trang thai: {$trangThaiText}";

        try {
            $telegram->guiTinNhan($tinNhan->ma_kenh, $thongBao);
            Log::info('GHN webhook gui thong bao Telegram thanh cong', [
                'ma_kenh' => $tinNhan->ma_kenh,
            ]);
        } catch (\Throwable $e) {
            Log::error('GHN webhook khong the gui Telegram', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
