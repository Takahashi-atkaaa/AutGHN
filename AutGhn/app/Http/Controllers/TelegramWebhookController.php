<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\KienHang;
use App\Models\ShopGhn;
use App\Models\TinNhanTelegram;
use App\Models\VanDon;
use App\Services\AIOrderParserService;
use App\Services\GhnService;
use App\Services\TaoDonHangService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Ket qua phan tich kich thuoc kien hang.
 * - OK: du D x R x C va can nang
 * - THIEU_CAN_NANG: co D x R x C nhung khong co can nang -> can hoi lai
 * - SAI_DINH_DANG: khong parse duoc
 */
enum ParseKichThuocResult: string
{
    case OK = 'ok';
    case THIEU_CAN_NANG = 'thieu_can_nang';
    case SAI_DINH_DANG = 'sai_dinh_dang';
}

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $telegram, AIOrderParserService $ai): JsonResponse
    {
        // #region agent log
        $this->dlog('H2_request_in', ['url' => $request->fullUrl(), 'has_msg' => $request->has('message'), 'has_cb' => $request->has('callback_query')]);
        // #endregion
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['success' => false, 'message' => 'Khong hop le'], 403);
        }

        $data = $request->json()->all();

        // Xu ly callback_query tu inline keyboard
        if (isset($data['callback_query'])) {
            return $this->handleCallback($data['callback_query'], $telegram);
        }

        $tinNhan = $data['message'] ?? $data['edited_message'] ?? null;
        if (! is_array($tinNhan) || ! isset($tinNhan['message_id'], $tinNhan['chat']['id'])) {
            return response()->json(['success' => true, 'message' => 'Bo qua su kien khong phai tin nhan']);
        }

        // Xu ly lenh khi user go "/" (vi du: /start, /help, /cancel).
        // Phan nhanh o day truoc khi luu tin nhan vao DB va truoc khi goi AI.
        if (isset($tinNhan['text']) && is_string($tinNhan['text']) && str_starts_with(ltrim($tinNhan['text']), '/')) {
            return $this->xuLyLenh($tinNhan, $telegram);
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

        // #region agent log
        $this->dlog('H1_msg_received', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'wasRecentlyCreated' => $banGhi->wasRecentlyCreated,
            'noi_dung' => $noiDung,
        ]);
        // #endregion

        Log::info('Telegram webhook da tiep nhan', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'moi' => $banGhi->wasRecentlyCreated,
            'trang_thai' => $banGhi->trang_thai,
        ]);

        // Neu dang cho sua thi xu ly tin nhan tra loi
        // Tim ban ghi dang cho sua trong cung chat
        if ($banGhi->wasRecentlyCreated && $noiDung !== '') {
            $banGhiDangSua = TinNhanTelegram::where('ma_kenh', $tinNhan['chat']['id'])
                ->where('trang_thai', 'cho_sua')
                ->orderBy('id', 'desc')
                ->first();

            if ($banGhiDangSua) {
                return $this->xuLyTinNhanTraLoi($banGhiDangSua, $noiDung, $telegram);
            }
        }

        // Neu don hang dang cho nhap kien / cho can nang / da nhap kien (cho chon shop) thi xu ly,
        // khong goi AI
        if ($banGhi->wasRecentlyCreated && $noiDung !== '') {
            $banGhiTrongFlow = TinNhanTelegram::where('ma_kenh', (string) $tinNhan['chat']['id'])
                ->whereIn('trang_thai', ['da_xac_nhan', 'cho_sua'])
                ->whereHas('donHang', function ($query) {
                    $query->whereIn('trang_thai', ['cho_kien_hang', 'cho_can_nang', 'da_nhap_kien']);
                })
                ->orderBy('id', 'desc')
                ->first();

            // #region agent log
            $this->dlog('H4_flow_lookup', [
                'chat_id' => (string) $tinNhan['chat']['id'],
                'found_id' => $banGhiTrongFlow?->id,
                'found_trang_thai' => $banGhiTrongFlow?->trang_thai,
                'noi_dung' => $noiDung,
            ]);
            // #endregion

            if ($banGhiTrongFlow) {
                return $this->xuLyTinNhanTrongFlowTaoDon($banGhiTrongFlow, $noiDung, $telegram);
            }
        }

        if ($banGhi->wasRecentlyCreated && $noiDung !== '') {
            // #region agent log
            $this->dlog('H3_ai_path', ['ma_tin_nhan' => $banGhi->ma_tin_nhan, 'noi_dung' => $noiDung]);
            // #endregion
            try {
                $duLieuAi = $ai->phanTich($noiDung);
                $banGhi->update([
                    'du_lieu_ai' => $duLieuAi,
                    'trang_thai' => 'da_phan_tich',
                ]);
                $this->guiKetQuaPhanTich($banGhi, $telegram);
            } catch (\Throwable $exception) {
                $banGhi->update(['trang_thai' => 'loi']);
                Log::error('Khong the phan tich tin nhan Telegram', [
                    'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                    'loi' => $exception->getMessage(),
                ]);
                try {
                    $telegram->guiTinNhan($tinNhan['chat']['id'], 'Khong the phan tich tin nhan luc nay. Vui long thu lai.');
                } catch (\Throwable) {
                    Log::error('Khong the gui thong bao loi Telegram');
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Ghi NDJSON vao debug log cua session nay.
     * Ghi vao storage/logs (cung quyen voi laravel.log) de tranh phai chmod .cursor/.
     */
    private function dlog(string $hypothesisId, array $data): void
    {
        $path = storage_path('logs/debug-20752c.log');
        $payload = [
            'sessionId' => '20752c',
            'id' => 'log_' . (int) (microtime(true) * 1000),
            'timestamp' => (int) (microtime(true) * 1000),
            'location' => 'TelegramWebhookController.php',
            'message' => 'instrumented',
            'data' => $data,
            'runId' => 'investigation',
            'hypothesisId' => $hypothesisId,
        ];
        try {
            file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // ignore
        }
    }

    private function handleCallback(array $callback, TelegramService $telegram): JsonResponse
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        $data = (string) ($callback['data'] ?? '');

        if ($callbackId === '' || $chatId === '' || $messageId === 0) {
            return response()->json(['success' => false, 'message' => 'Du lieu callback khong hop le']);
        }

        // Tim ban ghi theo chat_id - lay ban ghi moi nhat o trang thai da_phan_tich hoac cac trang thai cho_*
        // Vi message_id trong callback thuoc ve tin nhan BOT gui (moi),
        // con ma_tin_nhan trong DB thuoc ve tin nhan goc cua user
        $banGhi = TinNhanTelegram::where('ma_kenh', $chatId)
            ->whereIn('trang_thai', ['da_phan_tich', 'da_xac_nhan'])
            ->orderBy('id', 'desc')
            ->first();

        if (! $banGhi) {
            $telegram->traLoiCallback($callbackId, 'Tin nhan nay khong con chap nhan thao tac.');
            return response()->json(['success' => true]);
        }

        Log::info('Telegram callback', [
            'callback_id' => $callbackId,
            'data' => $data,
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'trang_thai' => $banGhi->trang_thai,
        ]);

        // Xu ly action theo trang thai hien tai
        if ($banGhi->trang_thai === 'da_phan_tich') {
            if ($data === 'xac_nhan') {
                return $this->xuLyXacNhan($banGhi, $callbackId, $telegram);
            }
            if ($data === 'huy') {
                return $this->xuLyHuy($banGhi, $callbackId, $telegram);
            }
            if (str_starts_with($data, 'sua:')) {
                $field = substr($data, 4);
                return $this->xuLyBatDauSua($banGhi, $field, $callbackId, $telegram);
            }
        }

        // Xử lý callback cho don_hang đã tạo
        if ($banGhi->trang_thai === 'da_xac_nhan') {
            $donHang = DonHang::where('tin_nhan_telegram_id', $banGhi->id)->first();

            if ($donHang) {
                // Xử lý chọn shop
                if (str_starts_with($data, 'shop:')) {
                    $shopId = (int) substr($data, 5);
                    return $this->xuLyChonShop($banGhi, $shopId, $callbackId, $telegram, app(GhnService::class));
                }

                if ($data === 'tao_van_don') {
                    return $this->xuLyTaoVanDon($banGhi, $donHang, $callbackId, $telegram, app(GhnService::class));
                }
                if ($data === 'huy_don') {
                    return $this->xuLyHuyDon($banGhi, $donHang, $callbackId, $telegram);
                }
            } else {
                // Chưa có don_hang - có thể đang ở trạng thái chờ nhập kích thước
                // Bỏ qua các callback không hợp lệ
            }
        }

        $telegram->traLoiCallback($callbackId, 'Thao tac khong hop le cho trang thai nay.');
        return response()->json(['success' => true]);
    }

    private function xuLyXacNhan(TinNhanTelegram $banGhi, string $callbackId, TelegramService $telegram): JsonResponse
    {
        try {
            // Tạo don_hang từ dữ liệu AI
            $donHang = app(TaoDonHangService::class)->taoDonHang($banGhi);

            // Cập nhật trạng thái tin nhắn
            $banGhi->update(['trang_thai' => 'da_xac_nhan']);

            // Thông báo thành công
            $telegram->traLoiCallback($callbackId, "Da xac nhan. Don hang: {$donHang->ma_don_noi_bo}");

            // Hỏi nhập kích thước kiện hàng
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                "Don hang {$donHang->ma_don_noi_bo}\n\n"
                . "Nhap kich thuoc kien hang (VD: 20x30x10 500g):\n"
                . "Dai x Rong x Cao (cm) [Can nang (gram)]\n\n"
                . "VD: 20x30x10 500g"
            );

            Log::info('Da xac nhan va tao don hang', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'don_hang_id' => $donHang->id,
                'ma_don' => $donHang->ma_don_noi_bo,
            ]);
        } catch (InvalidArgumentException $e) {
            $telegram->traLoiCallback($callbackId, 'Loi: ' . $e->getMessage());
            Log::warning('Khong the tao don hang', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'loi' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function xuLyHuy(TinNhanTelegram $banGhi, string $callbackId, TelegramService $telegram): JsonResponse
    {
        $banGhi->update(['trang_thai' => 'da_huy']);

        $telegram->traLoiCallback($callbackId);
        $telegram->guiTinNhan($banGhi->layMaKenh(), 'Da huy tin nhan nay.');

        Log::info('Tin nhan da huy', ['ma_tin_nhan' => $banGhi->ma_tin_nhan]);

        return response()->json(['success' => true]);
    }

    private function xuLyBatDauSua(TinNhanTelegram $banGhi, string $field, string $callbackId, TelegramService $telegram): JsonResponse
    {
        $cacFieldHopLe = ['ten', 'sdt', 'dc', 'hang', 'cod', 'ghichu'];
        if (! in_array($field, $cacFieldHopLe, true)) {
            $telegram->traLoiCallback($callbackId, 'Truong khong hop le.');
            return response()->json(['success' => true]);
        }

        $banGhi->batDauSuaField($field);
        $banGhi->save();

        $tenField = match ($field) {
            'ten' => 'ten khach hang',
            'sdt' => 'so dien thoai',
            'dc' => 'dia chi',
            'hang' => 'danh sach hang (vi du: 2 x ao phong, 1 x quan jean)',
            'cod' => 'so tien COD (vi du: 350000 hoac 350k)',
            'ghichu' => 'ghi chu giao hang',
            default => $field,
        };

        $telegram->traLoiCallback($callbackId, "Vui long nhap {$tenField} moi:");
        $telegram->guiTinNhan($banGhi->layMaKenh(), "Nhap {$tenField} moi:");

        Log::info('Bat dau sua field', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'field' => $field,
        ]);

        return response()->json(['success' => true]);
    }

    private function xuLyTinNhanTraLoi(TinNhanTelegram $banGhi, string $noiDung, TelegramService $telegram): JsonResponse
    {
        $field = $banGhi->layFieldDangSua();
        if ($field === null) {
            return response()->json(['success' => true]);
        }

        $ghiChuLoi = '';

        // Map field hien thi sang field trong du_lieu_ai
        $fieldMap = [
            'ten' => 'customer_name',
            'sdt' => 'phone',
            'dc' => 'address',
            'hang' => 'items',
            'cod' => 'cod',
            'ghichu' => 'note',
        ];

        $aiField = $fieldMap[$field] ?? $field;

        // Xu ly va chuan hoa gia tri theo tung loai
        $giaTriMoi = match ($field) {
            'sdt' => $this->chuanHoaSoDienThoai($noiDung),
            'cod' => $this->chuanHoaCod($noiDung),
            'hang' => $this->chuanHoaItems($noiDung),
            'ten', 'dc', 'ghichu' => $this->chuanHoaText($noiDung),
            default => $noiDung,
        };

        $banGhi->datField($aiField, $giaTriMoi);
        $banGhi->ketThucSua();
        $banGhi->save();

        if ($ghiChuLoi !== '') {
            $telegram->guiTinNhan($banGhi->layMaKenh(), $ghiChuLoi);
        }

        $this->guiKetQuaPhanTich($banGhi, $telegram);

        Log::info('Da cap nhat field', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'field' => $aiField,
            'gia_tri' => $giaTriMoi,
        ]);

        return response()->json(['success' => true]);
    }

    private function chuanHoaSoDienThoai(string $input): ?string
    {
        // Chi giu chuoi chu so
        $so = preg_replace('/\D/', '', $input);
        return $so !== '' ? $so : null;
    }

    private function chuanHoaCod(string $input): ?float
    {
        $input = trim(mb_strtolower($input));

        // Neu rong hoac "null", "khong", "khong co" thi tra ve null
        if ($input === '' || $input === 'null' || $input === 'khong' || $input === 'khong co') {
            return null;
        }

        // Xu ly dinh dang: 350k, 350k, 1tr2, 1tr, 350.000, 350000
        $so = $input;

        // Xu ly "1tr2" hoac "1tr" (1 trieu 200 hoac 1 trieu)
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*tr(?:i?eu)?\s*(?:(\d+)k?)?$/i', $so, $m)) {
            $giaTri = (float) str_replace(',', '.', $m[1]) * 1000000;
            if (isset($m[2]) && $m[2] !== '') {
                $giaTri += (int) $m[2] * 1000;
            }
            return $giaTri > 0 ? $giaTri : null;
        }

        // Xu ly "350k" (350 nghin)
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*k\b$/i', $so, $m)) {
            $giaTri = (float) str_replace(',', '.', $m[1]) * 1000;
            return $giaTri > 0 ? $giaTri : null;
        }

        // Loai bo cac ky tu khong phai so
        $so = preg_replace('/[^\d.,]/', '', $so);

        // Thay dau phay bang dau cham neu can
        $so = str_replace(',', '.', $so);

        // Neu co nhieu dau cham, lay phan truoc dau cham dau tien
        if (substr_count($so, '.') > 1) {
            $parts = explode('.', $so);
            $so = $parts[0];
        }

        $giaTri = (float) $so;

        return $giaTri > 0 ? $giaTri : null;
    }

    private function chuanHoaItems(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $items = [];
        $dongItems = preg_split('/\r\n|\n|\r/', $input);

        foreach ($dongItems as $dong) {
            $dong = trim($dong);
            if ($dong === '') continue;

            // Tim so luong o dau: "2 x ao" hoac "2x ao" hoac "2. ao" hoac "2) ao"
            if (preg_match('/^(\d+)\s*[xX\.)\-]\s*(.+)$/', $dong, $matches)) {
                $soLuong = (int) $matches[1];
                $tenHang = trim($matches[2]);
            } elseif (preg_match('/^(\d+)\s+(.+)$/', $dong, $matches)) {
                $soLuong = (int) $matches[1];
                $tenHang = trim($matches[2]);
            } else {
                $soLuong = 1;
                $tenHang = $dong;
            }

            if ($tenHang !== '') {
                $items[] = [
                    'name' => $tenHang,
                    'quantity' => $soLuong,
                ];
            }
        }

        return $items;
    }

    private function chuanHoaText(string $input): ?string
    {
        $input = trim($input);
        return $input !== '' ? $input : null;
    }

    private function guiKetQuaPhanTich(TinNhanTelegram $banGhi, TelegramService $telegram): void
    {
        $duLieu = $banGhi->layDuLieuAi();
        $noiDung = $this->dinhDangKetQua($duLieu);

        $phim = [
            [
                ['text' => 'Xac nhan', 'callback_data' => 'xac_nhan'],
                ['text' => 'Sua ten', 'callback_data' => 'sua:ten'],
                ['text' => 'Sua SDT', 'callback_data' => 'sua:sdt'],
            ],
            [
                ['text' => 'Sua DC', 'callback_data' => 'sua:dc'],
                ['text' => 'Sua hang', 'callback_data' => 'sua:hang'],
                ['text' => 'Sua COD', 'callback_data' => 'sua:cod'],
            ],
            [
                ['text' => 'Sua ghi chu', 'callback_data' => 'sua:ghichu'],
                ['text' => 'Huy', 'callback_data' => 'huy'],
            ],
        ];

        $telegram->guiTinNhanCoPhim($banGhi->layMaKenh(), $noiDung, $phim);
    }

    private function dinhDangKetQua(array $duLieu): string
    {
        $hang = collect($duLieu['items'] ?? [])
            ->map(fn (array $item) => "{$item['quantity']} x {$item['name']}")
            ->implode("\n");

        return "KET QUA PHAN TICH\n\n"
            . 'Ten: '.($duLieu['customer_name'] ?? 'Chua co')."\n"
            . 'SDT: '.($duLieu['phone'] ?? 'Chua co')."\n"
            . 'Dia chi: '.($duLieu['address'] ?? 'Chua co')."\n\n"
            . "Hang:\n".($hang !== '' ? $hang : 'Chua co')."\n\n"
            . 'COD: '.($duLieu['cod'] !== null ? number_format((float) $duLieu['cod'], 0, ',', '.').'d' : 'Chua co')."\n"
            . 'Ghi chu: '.($duLieu['note'] ?? 'Chua co');
    }

    /**
     * Xu ly cac lenh (commands) ma user go trong Telegram: /start, /help, /cancel.
     * Bo qua viec luu tin nhan vao DB va khong goi AI.
     */
    private function xuLyLenh(array $tinNhan, TelegramService $telegram): JsonResponse
    {
        $chatId = $tinNhan['chat']['id'];
        $text = trim((string) ($tinNhan['text'] ?? ''));
        // Lay phan dau tien (command), bo qua cac tham so phia sau.
        $command = strtolower((string) (explode(' ', $text)[0] ?? ''));
        // Loai bo suffix @username_bot neu co (vd /start@MyBot).
        $command = (string) preg_replace('/@\w+$/', '', $command);

        $banPhim = [
            [['text' => '/start'], ['text' => '/help']],
            [['text' => '/cancel']],
        ];

        switch ($command) {
            case '/start':
                $telegram->guiTinNhanCoBanPhim(
                    $chatId,
                    "Xin chao! Day la bot GHN247.\n\n"
                    . "Ban co the:\n"
                    . "- Gui tin nhan don hang binh thuong (vi du: 'Nguyen Van A, 0912345678, 123 Nguyen Trai Q1, 1 ao phong 250k').\n"
                    . "- Go /help de xem huong dan chi tiet.\n"
                    . "- Go /cancel neu muon huy thao tac dang thuc hien.",
                    $banPhim,
                    motLan: true,
                );
                break;

            case '/help':
                $noiDung = "=== HUONG DAN SU DUNG ===\n\n"
                    . "1. Gui tin nhan don hang theo dang:\n"
                    . "   Ten, SDT, dia chi, hang, tien COD\n"
                    . "   Vi du: 'Nguyen Van A, 0912345678, 123 Nguyen Trai Q1, 1 ao phong 250k'\n\n"
                    . "2. Bot se phan tich va hoi ban xac nhan.\n\n"
                    . "3. Sau khi xac nhan, nhap kich thuoc kien hang:\n"
                    . "   Vi du: '20x30x10 500g'\n\n"
                    . "4. Chon shop GHN de tinh phi ship.\n\n"
                    . "5. Xac nhan de tao van don.";
                $telegram->guiTinNhanCoBanPhim($chatId, $noiDung, $banPhim, motLan: true);
                break;

            case '/cancel':
                $tinNhanTrongFlow = TinNhanTelegram::where('ma_kenh', (string) $chatId)
                    ->whereIn('trang_thai', ['da_phan_tich', 'da_xac_nhan', 'cho_sua'])
                    ->orderBy('id', 'desc')
                    ->first();

                if (!$tinNhanTrongFlow) {
                    $telegram->guiTinNhan($chatId, 'Khong co thao tac nao dang thuc hien.');
                    break;
                }

                // Huy don hang lien ket neu co (o cac trang thai chua tao van don that)
                $donHang = DonHang::where('tin_nhan_telegram_id', $tinNhanTrongFlow->id)
                    ->whereIn('trang_thai', ['cho_kien_hang', 'cho_can_nang', 'da_nhap_kien', 'da_tinh_phi', 'da_chon_shop'])
                    ->orderBy('id', 'desc')
                    ->first();

                $chiTiet = [];

                if ($donHang) {
                    // Xoa cung KienHang (du lieu staging, chua gui len GHN)
                    $soKienHang = $donHang->kienHang()->count();
                    if ($soKienHang > 0) {
                        $donHang->kienHang()->delete();
                        $chiTiet[] = "xoa {$soKienHang} kien hang";
                    }

                    // Xoa cung LanTinhPhi (ket qua tinh phi tam)
                    $soLanTinhPhi = $donHang->lanTinhPhis()->count();
                    if ($soLanTinhPhi > 0) {
                        $donHang->lanTinhPhis()->delete();
                        $chiTiet[] = "xoa {$soLanTinhPhi} lan tinh phi";
                    }

                    // Danh dau don_hang da huy
                    $donHang->update(['trang_thai' => 'da_huy']);
                    $chiTiet[] = "don hang {$donHang->ma_don_noi_bo}";
                }

                // Xoa co tam _kich_thuoc_cho_can_nang neu co
                $duLieuAi = $tinNhanTrongFlow->du_lieu_ai ?? [];
                if (isset($duLieuAi['_kich_thuoc_cho_can_nang'])) {
                    unset($duLieuAi['_kich_thuoc_cho_can_nang']);
                    $tinNhanTrongFlow->update(['du_lieu_ai' => $duLieuAi]);
                }

                // Danh dau tin nhan da huy
                $tinNhanTrongFlow->update(['trang_thai' => 'da_huy']);

                $thongBao = 'Da huy thao tac dang thuc hien.';
                if (!empty($chiTiet)) {
                    $thongBao .= "\n" . implode(', ', $chiTiet) . '.';
                }
                $telegram->guiTinNhan($chatId, $thongBao);

                Log::info('Huy thao tac qua /cancel', [
                    'ma_tin_nhan' => $tinNhanTrongFlow->ma_tin_nhan,
                    'don_hang_id' => $donHang?->id,
                    'ma_don' => $donHang?->ma_don_noi_bo,
                ]);
                break;

            default:
                $telegram->guiTinNhan($chatId, 'Lenh khong hop le. Go /help de xem huong dan.');
                break;
        }

        Log::info('Telegram command', [
            'chat_id' => $chatId,
            'command' => $command,
        ]);

        return response()->json(['success' => true]);
    }

    // ==================== CAC METHOD MOI CHO TAO DON HANG ====================

    private function xuLyChonShop(TinNhanTelegram $banGhi, int $shopId, string $callbackId, TelegramService $telegram, GhnService $ghnService): JsonResponse
    {
        $shop = ShopGhn::find($shopId);
        if (!$shop) {
            $telegram->traLoiCallback($callbackId, 'Shop khong ton tai.');
            return response()->json(['success' => true]);
        }

        // Tìm don_hang liên kết
        $donHang = DonHang::where('tin_nhan_telegram_id', $banGhi->id)
            ->where('trang_thai', 'da_nhap_kien')
            ->first();

        if (!$donHang) {
            $telegram->traLoiCallback($callbackId, 'Loi: Khong tim thay don hang. Vui long xac nhan lai.');
            return response()->json(['success' => true]);
        }

        $kienHang = $donHang->kienHang;
        if (!$kienHang) {
            $telegram->traLoiCallback($callbackId, 'Loi: Khong tim thay thong tin kien hang.');
            return response()->json(['success' => true]);
        }

        // Phan hoi ngay de tranh Telegram timeout (callback chi hop le ~30s)
        $telegram->traLoiCallback($callbackId, "Dang tinh phi cho shop {$shop->ten_shop}...");

        $duLieuAi = $banGhi->du_lieu_ai;
        $diaChiNhan = $duLieuAi['address'] ?? '';
        $cod = (int) ($duLieuAi['cod'] ?? 0);

        // Tính phí ship
        $phiShip = 0;
        $dichVuText = '';
        $loiPhiShip = null;

        if ($diaChiNhan) {
            try {
                $ketQuaPhi = $ghnService->tinhPhiShip($shop, $donHang, $kienHang, $diaChiNhan, $cod);
            } catch (\Throwable $e) {
                Log::error('Loi tinh phi ship', [
                    'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                    'don_hang_id' => $donHang->id,
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
                // Hien thi loi qua alert popup (show_alert = true) de khong spam them tin nhan
                $telegram->traLoiCallback(
                    $callbackId,
                    "Loi khi tinh phi ship: {$e->getMessage()}",
                    hienThiAlert: true,
                );
                return response()->json(['success' => true]);
            }

            if (!empty($ketQuaPhi['success']) && !empty($ketQuaPhi['services'])) {
                $dichVu = $ketQuaPhi['services'][0];
                $phiShip = $dichVu['fee'] ?? 0;
                $dichVuText = $dichVu['short_name'] ?? '';

                // Cập nhật trạng thái don_hang
                $donHang->update([
                    'shop_ghn_id' => $shop->id,
                    'trang_thai' => 'da_tinh_phi',
                ]);
            } else {
                $loiPhiShip = $ketQuaPhi['error'] ?? 'Loi khong xac dinh';
                Log::warning('Tinh phi ship that bai', [
                    'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                    'don_hang_id' => $donHang->id,
                    'loi' => $loiPhiShip,
                ]);

                // Van cap nhat shop da chon de khong bi ket
                $donHang->update([
                    'shop_ghn_id' => $shop->id,
                    'trang_thai' => 'da_chon_shop',
                ]);
                $phiShip = 0;
                $dichVuText = 'Khong tinh duoc';
            }
        } else {
            $donHang->update([
                'shop_ghn_id' => $shop->id,
                'trang_thai' => 'da_chon_shop',
            ]);
        }

        // Lưu thông tin tính phí vào don_hang (tạm thời)
        $donHang->update([
            'phi_ship' => $phiShip,
            'dich_vu_ghn' => $dichVuText,
        ]);

        // Hien thi canh bao loi truc tiep trong tin tong ket (khong gui them tin rieng de tranh spam)
        $canhBao = '';
        if ($loiPhiShip !== null) {
            $canhBao = "\n⚠️ Canh bao: {$loiPhiShip}\n"
                . "Ban van co the tao van don, nhung can kiem tra lai ma quan/huyen cua shop va dia chi nguoi nhan.\n";
        }

        // Hiển thị tổng kết
        $tongCong = $phiShip + $cod;
        $hangText = collect($duLieuAi['items'] ?? [])
            ->map(fn($item) => "{$item['quantity']} x {$item['name']}")
            ->implode("\n");

        $text = "=== XAC NHAN TAO VAN DON ===\n\n";
        $text .= "Don hang: {$donHang->ma_don_noi_bo}\n";
        $text .= "Shop: {$shop->ten_shop}\n\n";
        $text .= "Nguoi nhan: {$duLieuAi['customer_name']}\n";
        $text .= "SDT: {$duLieuAi['phone']}\n";
        $text .= "Dia chi: {$diaChiNhan}\n\n";
        $text .= "Hang:\n{$hangText}\n\n";
        $text .= "Kich thuoc: {$kienHang->dai}x{$kienHang->rong}x{$kienHang->cao} cm, {$kienHang->can_nang}g\n";
        $text .= "COD: " . number_format($cod) . " VND\n";
        $text .= "Phi ship: " . number_format($phiShip) . " VND";
        if ($dichVuText) {
            $text .= " ({$dichVuText})";
        }
        $text .= "\n";
        $text .= "Tong cong: " . number_format($tongCong) . " VND\n";
        $text .= $canhBao;

        $phims = [
            [['text' => 'Tao van don', 'callback_data' => 'tao_van_don']],
            [['text' => 'Huy', 'callback_data' => 'huy_don']],
        ];

        $telegram->guiTinNhanCoPhim($banGhi->layMaKenh(), $text, $phims);

        Log::info('Da chon shop, cho tao van don', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'don_hang_id' => $donHang->id,
            'shop_id' => $shopId,
            'phi_ship' => $phiShip,
        ]);

        return response()->json(['success' => true]);
    }

    private function xuLyTaoVanDon(TinNhanTelegram $banGhi, DonHang $donHang, string $callbackId, TelegramService $telegram, GhnService $ghnService): JsonResponse
    {
        $shop = $donHang->shopGhn;
        if (!$shop) {
            $telegram->traLoiCallback($callbackId, 'Loi: Khong tim thay shop.');
            return response()->json(['success' => true]);
        }

        $kienHang = $donHang->kienHang;
        if (!$kienHang) {
            $telegram->traLoiCallback($callbackId, 'Loi: Khong tim thay thong tin kien hang.');
            return response()->json(['success' => true]);
        }

        // Kiểm tra đã có van_don chưa
        if ($donHang->vanDon && $donHang->vanDon->ma_van_don) {
            $telegram->traLoiCallback($callbackId, 'Don hang da co van don: ' . $donHang->vanDon->ma_van_don);
            return response()->json(['success' => true]);
        }

        try {
            // Tạo van_don
            $vanDon = VanDon::create([
                'don_hang_id' => $donHang->id,
                'shop_ghn_id' => $shop->id,
                'trang_thai' => 'dang_tao',
            ]);

            // Gọi GHN tạo vận đơn
            $ketQuaGhn = $ghnService->taoVanDon($shop, $donHang, $kienHang);

            if ($ketQuaGhn['success']) {
                $vanDon->update([
                    'ma_van_don' => $ketQuaGhn['order_code'],
                    'trang_thai' => 'da_tao',
                    'du_lieu_tao' => $ketQuaGhn['data'] ?? null,
                    'thoi_gian_tao' => now(),
                ]);

                $donHang->update(['trang_thai' => 'da_tao_van_don']);

                $telegram->traLoiCallback($callbackId, 'Tao van don thanh cong!');
                $thongBao = "=== TAO VAN DON THANH CONG ===\n\n";
                $thongBao .= "Ma don noi bo: {$donHang->ma_don_noi_bo}\n";
                $thongBao .= "Ma van don GHN: {$ketQuaGhn['order_code']}";
            } else {
                $vanDon->update([
                    'trang_thai' => 'loi_tao',
                    'du_lieu_tao' => ['error' => $ketQuaGhn['error'] ?? 'Unknown error'],
                ]);

                $donHang->update(['trang_thai' => 'loi']);

                $telegram->traLoiCallback($callbackId, 'Tao van don that bai: ' . ($ketQuaGhn['error'] ?? 'Loi khong xac dinh'));
                $thongBao = "=== TAO VAN DON THAT BAI ===\n\n";
                $thongBao .= "Ma don noi bo: {$donHang->ma_don_noi_bo}\n";
                $thongBao .= "Loi: " . ($ketQuaGhn['error'] ?? 'Khong ro');
            }

            $telegram->guiTinNhan($banGhi->layMaKenh(), $thongBao);

            Log::info('Xu ly tao van don', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'don_hang_id' => $donHang->id,
                'ma_van_don' => $ketQuaGhn['order_code'] ?? null,
                'thanh_cong' => $ketQuaGhn['success'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Loi tao van don', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'don_hang_id' => $donHang->id,
                'error' => $e->getMessage(),
            ]);
            $telegram->traLoiCallback($callbackId, 'Loi: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    private function xuLyHuyDon(TinNhanTelegram $banGhi, DonHang $donHang, string $callbackId, TelegramService $telegram): JsonResponse
    {
        $donHang->update(['trang_thai' => 'da_huy']);
        $banGhi->update(['trang_thai' => 'da_huy']);

        $telegram->traLoiCallback($callbackId, 'Da huy don hang.');
        $telegram->guiTinNhan($banGhi->layMaKenh(), "Don hang {$donHang->ma_don_noi_bo} da bi huy.");

        Log::info('Da huy don hang', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'don_hang_id' => $donHang->id,
        ]);

        return response()->json(['success' => true]);
    }

    private function xuLyTinNhanTrongFlowTaoDon(TinNhanTelegram $banGhi, string $noiDung, TelegramService $telegram): JsonResponse
    {
        // Xu ly khi dang o trang thai da_xac_nhan hoac cho_sua (da co don_hang, cho nhap kich thuoc)
        if ($banGhi->trang_thai !== 'da_xac_nhan' && $banGhi->trang_thai !== 'cho_sua') {
            return response()->json(['success' => true]);
        }

        // Tim don_hang lien ket - cho ca 3 trang thai:
        // - cho_kien_hang: chua nhap kich thuoc
        // - cho_can_nang: da co kich thuoc, dang cho nhap can nang
        // - da_nhap_kien: da co kien, dang cho chon shop
        $donHang = DonHang::where('tin_nhan_telegram_id', $banGhi->id)
            ->whereIn('trang_thai', ['cho_kien_hang', 'cho_can_nang', 'da_nhap_kien'])
            ->orderBy('id', 'desc')
            ->first();

        if (!$donHang) {
            $telegram->guiTinNhan($banGhi->layMaKenh(), 'Loi: Khong tim thay don hang. Vui long xac nhan lai.');
            return response()->json(['success' => true]);
        }

        // === Nhanh 1: don_hang dang cho_can_nang (da co kich thuoc, cho nhap can nang) ===
        if ($donHang->trang_thai === 'cho_can_nang') {
            return $this->xuLyNhapCanNang($banGhi, $donHang, $noiDung, $telegram);
        }

        // === Nhanh 2: don_hang da co kien (da_nhap_kien) - bo qua parse, hien thi danh sach shop ===
        if ($donHang->trang_thai === 'da_nhap_kien') {
            return $this->hienThiDanhSachShop($banGhi, $donHang, $telegram);
        }

        // === Nhanh 3: cho_kien_hang - parse kich thuoc moi ===
        $ketQua = $this->phanTichKichThuoc($noiDung);

        if ($ketQua['status'] === ParseKichThuocResult::SAI_DINH_DANG) {
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                'Sai dinh dang. Nhap: Dai x Rong x Cao CanNang (gram)\n'
                . 'VD: 20x30x10 500g'
            );
            return response()->json(['success' => true]);
        }

        if ($ketQua['status'] === ParseKichThuocResult::THIEU_CAN_NANG) {
            // Luu tam kich thuoc vao du_lieu_ai, doi user nhap can nang
            $kt = $ketQua['kich_thuoc'];
            $duLieuHienTai = $banGhi->du_lieu_ai ?? [];
            $duLieuHienTai['_kich_thuoc_cho_can_nang'] = [
                'dai' => $kt['dai'],
                'rong' => $kt['rong'],
                'cao' => $kt['cao'],
            ];
            $banGhi->update(['du_lieu_ai' => $duLieuHienTai]);

            $donHang->update(['trang_thai' => 'cho_can_nang']);

            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                "Ban nhap kich thuoc {$kt['dai']}x{$kt['rong']}x{$kt['cao']} cm rui, nhung chua co can nang.\n\n"
                . "Nhap can nang bang gram (VD: 500) hoac kg (VD: 1.2kg):"
            );

            Log::info('Cho nhap can nang', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'don_hang_id' => $donHang->id,
                'kich_thuoc' => $kt,
            ]);

            return response()->json(['success' => true]);
        }

        // === Nhanh 4: OK - co du D x R x C va can nang, tao kien_hang luon ===
        $kt = $ketQua['kich_thuoc'];
        $kienHang = KienHang::create([
            'don_hang_id' => $donHang->id,
            'dai' => $kt['dai'],
            'rong' => $kt['rong'],
            'cao' => $kt['cao'],
            'can_nang' => $kt['can_nang'],
        ]);

        $donHang->update(['trang_thai' => 'da_nhap_kien']);

        Log::info('Da nhap kich thuoc, cho chon shop', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'don_hang_id' => $donHang->id,
            'kien_hang_id' => $kienHang->id,
            'kien_hang' => $kt,
        ]);

        return $this->hienThiDanhSachShop($banGhi, $donHang, $telegram);
    }

    /**
     * Xu ly khi don_hang dang o trang thai cho_can_nang.
     * User nhap mot so (gram) hoac co don vi (kg/g).
     * Lay kich thuoc da luu tam trong du_lieu_ai['_kich_thuoc_cho_can_nang'] de tao kien_hang.
     */
    private function xuLyNhapCanNang(TinNhanTelegram $banGhi, DonHang $donHang, string $noiDung, TelegramService $telegram): JsonResponse
    {
        $canNang = $this->parseCanNang($noiDung);

        if ($canNang === null) {
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                "Can nang khong hop le. Nhap lai:\n"
                . "- Gram: 500 hoac 500g\n"
                . "- Kg: 1.2kg hoac 1kg 200g"
            );
            return response()->json(['success' => true]);
        }

        // Gioi han can nang (GHN cho phep toi da 30000g = 30kg)
        if ($canNang <= 0 || $canNang > 30000) {
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                "Can nang phai tu 1g den 30000g (30kg). Ban nhap: {$canNang}g"
            );
            return response()->json(['success' => true]);
        }

        // Lay kich thuoc da luu tam
        $duLieuAi = $banGhi->du_lieu_ai ?? [];
        $ktCho = $duLieuAi['_kich_thuoc_cho_can_nang'] ?? null;

        if (!$ktCho) {
            // Loi: khong co kich thuoc tam -> reset ve cho_kien_hang
            Log::error('Khong tim thay kich thuoc tam khi dang cho can nang', [
                'ma_tin_nhan' => $banGhi->ma_tin_nhan,
                'don_hang_id' => $donHang->id,
            ]);
            $donHang->update(['trang_thai' => 'cho_kien_hang']);
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                'Co loi xay ra, vui long nhap lai kich thuoc kien hang (VD: 20x30x10 500g):'
            );
            return response()->json(['success' => true]);
        }

        // Tao kien_hang
        $kienHang = KienHang::create([
            'don_hang_id' => $donHang->id,
            'dai' => $ktCho['dai'],
            'rong' => $ktCho['rong'],
            'cao' => $ktCho['cao'],
            'can_nang' => $canNang,
        ]);

        // Xoa co tam trong du_lieu_ai
        unset($duLieuAi['_kich_thuoc_cho_can_nang']);
        $banGhi->update(['du_lieu_ai' => $duLieuAi]);

        $donHang->update(['trang_thai' => 'da_nhap_kien']);

        Log::info('Da nhap can nang, tao kien hang', [
            'ma_tin_nhan' => $banGhi->ma_tin_nhan,
            'don_hang_id' => $donHang->id,
            'kien_hang_id' => $kienHang->id,
            'can_nang' => $canNang,
        ]);

        // Hien thi danh sach shop
        return $this->hienThiDanhSachShop($banGhi, $donHang, $telegram);
    }

    private function hienThiDanhSachShop(TinNhanTelegram $banGhi, DonHang $donHang, TelegramService $telegram): JsonResponse
    {
        $kienHang = $donHang->kienHang;
        $kichThuocText = $kienHang
            ? "{$kienHang->dai}x{$kienHang->rong}x{$kienHang->cao} cm, {$kienHang->can_nang}g"
            : 'Khong xac dinh';

        $shops = ShopGhn::kichHoat()->get();

        if ($shops->isEmpty()) {
            $telegram->guiTinNhan(
                $banGhi->layMaKenh(),
                "Kich thuoc: {$kichThuocText}\n\n"
                . "Chua co shop GHN nao duoc cau hinh. Vui long lien he quan tri."
            );
            return response()->json(['success' => true]);
        }

        $phims = [];
        foreach ($shops as $shop) {
            $phims[] = [['text' => $shop->ten_shop, 'callback_data' => "shop:{$shop->id}"]];
        }

        $telegram->guiTinNhanCoPhim(
            $banGhi->layMaKenh(),
            "Kich thuoc: {$kichThuocText}\n\n"
            . "Chon shop GHN:",
            $phims
        );

        return response()->json(['success' => true]);
    }

    /**
     * Phan tich input kich thuoc kien hang. Tra ve:
     * - status: ParseKichThuocResult::OK | THIEU_CAN_NANG | SAI_DINH_DANG
     * - kich_thuoc: ['dai', 'rong', 'cao', 'can_nang' (neu OK)]
     *
     * Format hop le:
     * - "20x30x10 500g"   -> OK
     * - "20 x 30 x 10 500" -> OK
     * - "20-30-10-500"    -> OK
     * - "20x30x10"        -> THIEU_CAN_NANG
     * - "abc"             -> SAI_DINH_DANG
     *
     * KHONG tu uoc tinh can nang (goi phuong an A: hoi lai khi thieu).
     */
    private function phanTichKichThuoc(string $input): array
    {
        $input = trim($input);

        // Pattern day du: D x R x C [CanNang (g)]
        $patternDayDu = '/^(\d+)\s*[xX,\-]\s*(\d+)\s*[xX,\-]\s*(\d+)(?:\s*(?:[xX,\-]\s*)?(\d+)\s*g?)?$/i';
        // Pattern chi kich thuoc: D x R x C (khong co can nang)
        $patternKichThuoc = '/^(\d+)\s*[xX,\-]\s*(\d+)\s*[xX,\-]\s*(\d+)$/i';

        // Thu pattern day du truoc
        if (preg_match($patternDayDu, $input, $matches)) {
            $dai = (int) $matches[1];
            $rong = (int) $matches[2];
            $cao = (int) $matches[3];
            $canNang = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : null;

            if ($dai <= 0 || $rong <= 0 || $cao <= 0) {
                return ['status' => ParseKichThuocResult::SAI_DINH_DANG, 'kich_thuoc' => null];
            }

            if ($canNang === null) {
                return [
                    'status' => ParseKichThuocResult::THIEU_CAN_NANG,
                    'kich_thuoc' => ['dai' => $dai, 'rong' => $rong, 'cao' => $cao],
                ];
            }

            return [
                'status' => ParseKichThuocResult::OK,
                'kich_thuoc' => [
                    'dai' => $dai,
                    'rong' => $rong,
                    'cao' => $cao,
                    'can_nang' => $canNang,
                ],
            ];
        }

        // Thu pattern chi kich thuoc (de phan biet "thieu can nang" voi "sai dinh dang")
        if (preg_match($patternKichThuoc, $input, $matches)) {
            $dai = (int) $matches[1];
            $rong = (int) $matches[2];
            $cao = (int) $matches[3];

            if ($dai <= 0 || $rong <= 0 || $cao <= 0) {
                return ['status' => ParseKichThuocResult::SAI_DINH_DANG, 'kich_thuoc' => null];
            }

            return [
                'status' => ParseKichThuocResult::THIEU_CAN_NANG,
                'kich_thuoc' => ['dai' => $dai, 'rong' => $rong, 'cao' => $cao],
            ];
        }

        return ['status' => ParseKichThuocResult::SAI_DINH_DANG, 'kich_thuoc' => null];
    }

    /**
     * Parse can nang tu input don gian (chi chua so + don vi gram/kg).
     * Tra ve gram (int) hoac null neu khong hop le.
     *
     * Format hop le:
     * - "500"   -> 500
     * - "500g"  -> 500
     * - "1.2kg" -> 1200
     * - "1kg"   -> 1000
     * - "1kg 200g" -> 1200
     * - "1kg200" -> 1200
     */
    private function parseCanNang(string $input): ?int
    {
        $input = trim(mb_strtolower($input));
        if ($input === '') {
            return null;
        }

        // Loai bo khoang trang thua ngoai cung
        $input = preg_replace('/\s+/', ' ', $input);

        // Dinh dang "Xkg [Ygg]" hoac "Xkg"
        // Vi du: "1.2kg 200g" -> kg=1.2, g=200
        if (preg_match('/^(\d+(?:\.\d+)?)\s*kg(?:\s+(\d+)\s*g?)?$/i', $input, $m)) {
            $kg = (float) $m[1];
            $g = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            return (int) round($kg * 1000) + $g;
        }

        // Dinh dang "Xg" hoac chi so "X"
        if (preg_match('/^(\d+)\s*g?$/i', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
