<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\TinNhanTelegram;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TaoDonHangService
{
    /**
     * Tạo đơn hàng từ tin nhắn Telegram đã xác nhận.
     *
     * @param TinNhanTelegram $tinNhan Tin nhắn đã xác nhận (trang_thai = da_phan_tich)
     * @return DonHang Đơn hàng đã tạo
     * @throws InvalidArgumentException Nếu dữ liệu không hợp lệ
     */
    public function taoDonHang(TinNhanTelegram $tinNhan): DonHang
    {
        // 1. Kiểm tra trạng thái tin nhắn
        if ($tinNhan->trang_thai !== 'da_phan_tich') {
            throw new InvalidArgumentException('Tin nhan chua duoc phan tich hoac da xu ly.');
        }

        // 2. Kiểm tra chưa có don_hang liên kết
        $donHangCu = DonHang::where('tin_nhan_telegram_id', $tinNhan->id)->first();
        if ($donHangCu) {
            throw new InvalidArgumentException('Da ton tai don hang cho tin nhan nay.');
        }

        // 3. Validate dữ liệu bắt buộc
        $duLieuAi = $tinNhan->layDuLieuAi();
        $this->validateDuLieu($duLieuAi);

        // 4. Tạo don_hang
        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'ma_don_noi_bo' => $this->taoMaDonNoiBo(),
            'ten_nguoi_nhan' => $duLieuAi['customer_name'],
            'so_dien_thoai' => $duLieuAi['phone'],
            'dia_chi' => $duLieuAi['address'],
            'noi_dung_hang' => $this->formatItems($duLieuAi['items'] ?? []),
            'tien_cod' => $duLieuAi['cod'] ?? 0,
            'ghi_chu' => $duLieuAi['note'] ?? null,
            'trang_thai' => 'cho_kien_hang', // Chờ nhập kích thước
        ]);

        Log::info('Da tao don hang', [
            'don_hang_id' => $donHang->id,
            'ma_don' => $donHang->ma_don_noi_bo,
            'tin_nhan_id' => $tinNhan->id,
        ]);

        return $donHang;
    }

    /**
     * Validate dữ liệu bắt buộc.
     *
     * @param array $duLieuAi Dữ liệu AI đã phân tích
     * @throws InvalidArgumentException Nếu thiếu dữ liệu bắt buộc
     */
    private function validateDuLieu(array $duLieuAi): void
    {
        // customer_name bắt buộc
        if (empty($duLieuAi['customer_name'])) {
            throw new InvalidArgumentException('Thieu ten nguoi nhan.');
        }

        // phone bắt buộc và phải là chuỗi số 9-12 ký tự
        $phone = $duLieuAi['phone'] ?? '';
        if (empty($phone)) {
            throw new InvalidArgumentException('Thieu so dien thoai.');
        }
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if (strlen($phoneDigits) < 9 || strlen($phoneDigits) > 12) {
            throw new InvalidArgumentException('So dien thoai khong hop le.');
        }

        // address bắt buộc, độ dài > 5
        $address = $duLieuAi['address'] ?? '';
        if (empty($address) || strlen(trim($address)) <= 5) {
            throw new InvalidArgumentException('Dia chi khong hop le hoac thieu.');
        }
    }

    /**
     * Tạo mã đơn nội bộ theo format: DH-YYYYMMDD-NNNN
     *
     * @return string Mã đơn nội bộ
     */
    private function taoMaDonNoiBo(): string
    {
        $prefix = 'DH' . date('Ymd');

        // Tìm số lớn nhất trong ngày
        $lastOrder = DonHang::where('ma_don_noi_bo', 'like', $prefix . '-%')
            ->orderBy('ma_don_noi_bo', 'desc')
            ->first();

        if ($lastOrder) {
            $parts = explode('-', $lastOrder->ma_don_noi_bo);
            $seq = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Format items thành text cho lưu vào noi_dung_hang.
     *
     * @param array $items Danh sách items
     * @return string Text định dạng
     */
    private function formatItems(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        return collect($items)
            ->map(fn(array $item) => "{$item['quantity']} x {$item['name']}")
            ->implode("\n");
    }
}
