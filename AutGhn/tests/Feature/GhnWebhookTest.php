<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\ShopGhn;
use App\Models\TinNhanTelegram;
use App\Models\VanDon;
use App\Models\WebhookGhn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhnWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function postGhnWebhook(array $payload)
    {
        return $this->withHeader('X-GHN-Webhook-Secret', 'test_secret')
            ->postJson('/api/ghn/webhook', $payload);
    }

    public function test_webhook_tao_ban_ghi_moi(): void
    {
        $response = $this->postGhnWebhook([
            'order_code' => 'TEST123',
            'status' => 'picking',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_ghn', [
            'ma_van_don' => 'TEST123',
        ]);
    }

    public function test_webhook_cho_phep_trang_thai_moi_va_chan_payload_trung(): void
    {
        // Tạo webhook đầu tiên
        $this->postGhnWebhook([
            'order_code' => 'TEST456',
            'status' => 'picking',
        ]);

        // Cùng mã vận đơn nhưng trạng thái mới phải được lưu riêng.
        $this->postGhnWebhook([
            'order_code' => 'TEST456',
            'status' => 'picked',
        ]);

        $this->assertEquals(2, WebhookGhn::where('ma_van_don', 'TEST456')->count());

        // Gửi lại đúng payload phải không tạo thêm bản ghi.
        $this->postGhnWebhook([
            'order_code' => 'TEST456',
            'status' => 'picked',
        ]);

        $this->assertEquals(2, WebhookGhn::where('ma_van_don', 'TEST456')->count());
    }

    public function test_webhook_cap_nhat_trang_thai_van_don(): void
    {
        // Tạo ShopGhn trước
        $shop = ShopGhn::create([
            'ten_shop' => 'Test Shop',
            'ma_shop' => 12345,
            'ma_quan_lay_hang' => 1442,
            'ma_phuong_lay_hang' => '21211',
            'ten_nguoi_gui' => 'Test Sender',
            'so_dien_thoai_nguoi_gui' => '0909123456',
            'dia_chi_lay_hang' => '123 Test Street',
            'kich_hoat' => true,
        ]);

        // Tạo tin nhắn, đơn hàng, vận đơn
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => 'webhook_test_1',
            'ma_kenh' => '123456',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_xac_nhan',
        ]);

        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'shop_ghn_id' => $shop->id,
            'ma_don_noi_bo' => 'DH001',
            'ten_nguoi_nhan' => 'Test',
            'so_dien_thoai' => '0909123456',
            'dia_chi' => 'Test address',
            'trang_thai' => 'da_tao_van_don',
        ]);

        $vanDon = VanDon::create([
            'don_hang_id' => $donHang->id,
            'shop_ghn_id' => $shop->id,
            'ma_van_don' => 'WEBHOOK001',
            'trang_thai' => 'da_tao',
        ]);

        // Gọi webhook
        $response = $this->postGhnWebhook([
            'order_code' => 'WEBHOOK001',
            'status' => 'picking',
            'message' => 'Dang lay hang',
        ]);

        $response->assertStatus(200);

        // Kiểm tra trạng thái được cập nhật
        $vanDon->refresh();
        $this->assertEquals('dang_lay_hang', $vanDon->trang_thai);
    }

    public function test_webhook_tao_lich_su_trang_thai(): void
    {
        // Tạo ShopGhn trước
        $shop = ShopGhn::create([
            'ten_shop' => 'Test Shop 2',
            'ma_shop' => 12346,
            'ma_quan_lay_hang' => 1442,
            'ma_phuong_lay_hang' => '21211',
            'ten_nguoi_gui' => 'Test Sender',
            'so_dien_thoai_nguoi_gui' => '0909123456',
            'dia_chi_lay_hang' => '123 Test Street',
            'kich_hoat' => true,
        ]);

        // Tạo dữ liệu cần thiết
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => 'webhook_test_2',
            'ma_kenh' => '123456',
            'noi_dung_goc' => 'Test order 2',
            'trang_thai' => 'da_xac_nhan',
        ]);

        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'shop_ghn_id' => $shop->id,
            'ma_don_noi_bo' => 'DH002',
            'ten_nguoi_nhan' => 'Test',
            'so_dien_thoai' => '0909123456',
            'dia_chi' => 'Test address',
            'trang_thai' => 'da_tao_van_don',
        ]);

        $vanDon = VanDon::create([
            'don_hang_id' => $donHang->id,
            'shop_ghn_id' => $shop->id,
            'ma_van_don' => 'WEBHOOK002',
            'trang_thai' => 'da_tao',
        ]);

        // Gọi webhook
        $this->postGhnWebhook([
            'order_code' => 'WEBHOOK002',
            'status' => 'delivery',
        ]);

        // Kiểm tra lịch sử được tạo
        $this->assertDatabaseHas('lich_su_trang_thai_van_don', [
            'van_don_id' => $vanDon->id,
            'ma_trang_thai_ghn' => 'delivery',
            'trang_thai' => 'dang_giao',
        ]);
    }

    public function test_webhook_khong_tim_thay_van_don(): void
    {
        // Gọi webhook với order_code không tồn tại
        $response = $this->postGhnWebhook([
            'order_code' => 'NOTFOUND123',
            'status' => 'picking',
        ]);

        $response->assertStatus(200);

        // Không có lỗi, vẫn lưu webhook
        $this->assertDatabaseHas('webhook_ghn', [
            'ma_van_don' => 'NOTFOUND123',
        ]);
    }

    public function test_webhook_map_trang_thai_delivered(): void
    {
        // Tạo ShopGhn trước
        $shop = ShopGhn::create([
            'ten_shop' => 'Test Shop 3',
            'ma_shop' => 12347,
            'ma_quan_lay_hang' => 1442,
            'ma_phuong_lay_hang' => '21211',
            'ten_nguoi_gui' => 'Test Sender',
            'so_dien_thoai_nguoi_gui' => '0909123456',
            'dia_chi_lay_hang' => '123 Test Street',
            'kich_hoat' => true,
        ]);

        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => 'webhook_test_3',
            'ma_kenh' => '123456',
            'noi_dung_goc' => 'Test order 3',
            'trang_thai' => 'da_xac_nhan',
        ]);

        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'shop_ghn_id' => $shop->id,
            'ma_don_noi_bo' => 'DH003',
            'ten_nguoi_nhan' => 'Test',
            'so_dien_thoai' => '0909123456',
            'dia_chi' => 'Test address',
            'trang_thai' => 'da_tao_van_don',
        ]);

        $vanDon = VanDon::create([
            'don_hang_id' => $donHang->id,
            'shop_ghn_id' => $shop->id,
            'ma_van_don' => 'WEBHOOK003',
            'trang_thai' => 'dang_giao',
        ]);

        // Gọi webhook với status delivered
        $this->postGhnWebhook([
            'order_code' => 'WEBHOOK003',
            'status' => 'delivered',
        ]);

        $vanDon->refresh();
        $this->assertEquals('da_giao', $vanDon->trang_thai);
    }

    public function test_webhook_map_trang_thai_cancelled(): void
    {
        // Tạo ShopGhn trước
        $shop = ShopGhn::create([
            'ten_shop' => 'Test Shop 4',
            'ma_shop' => 12348,
            'ma_quan_lay_hang' => 1442,
            'ma_phuong_lay_hang' => '21211',
            'ten_nguoi_gui' => 'Test Sender',
            'so_dien_thoai_nguoi_gui' => '0909123456',
            'dia_chi_lay_hang' => '123 Test Street',
            'kich_hoat' => true,
        ]);

        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => 'webhook_test_4',
            'ma_kenh' => '123456',
            'noi_dung_goc' => 'Test order 4',
            'trang_thai' => 'da_xac_nhan',
        ]);

        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'shop_ghn_id' => $shop->id,
            'ma_don_noi_bo' => 'DH004',
            'ten_nguoi_nhan' => 'Test',
            'so_dien_thoai' => '0909123456',
            'dia_chi' => 'Test address',
            'trang_thai' => 'da_tao_van_don',
        ]);

        $vanDon = VanDon::create([
            'don_hang_id' => $donHang->id,
            'shop_ghn_id' => $shop->id,
            'ma_van_don' => 'WEBHOOK004',
            'trang_thai' => 'da_tao',
        ]);

        // Gọi webhook với status cancelled
        $this->postGhnWebhook([
            'order_code' => 'WEBHOOK004',
            'status' => 'cancelled',
        ]);

        $vanDon->refresh();
        $this->assertEquals('da_huy', $vanDon->trang_thai);
    }
}
