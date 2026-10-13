<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KienHang;
use App\Models\LanTinhPhi;
use App\Models\ShopGhn;
use App\Models\VanDon;
use App\Services\GhnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhnServiceTest extends TestCase
{
    use RefreshDatabase;

    private GhnService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GhnService();
    }

    public function test_chuan_hoa_dia_chi_tra_ve_mang_rong(): void
    {
        // Hiện tại chưa implement - trả về mảng rỗng
        $result = $this->service->chuanHoaDiaChi('123 Duong ABC, Quan 1, TP HCM');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('district_id', $result);
        $this->assertArrayHasKey('ward_code', $result);
    }

    public function test_lay_phieu_in_tra_loi_error(): void
    {
        // Khi không có GHN token hợp lệ, nên trả về error
        $result = $this->service->layPhieuIn('TEST123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('loai', $result);
        $this->assertArrayHasKey('du_lieu', $result);
    }

    public function test_tinh_phi_tu_choi_dia_chi_chua_co_ma_ghn(): void
    {
        Http::fake();

        $shop = ShopGhn::create([
            'ten_shop' => 'Shop test',
            'ma_shop' => '12345',
            'ma_quan_lay_hang' => '1442',
            'ma_phuong_lay_hang' => '21211',
        ]);
        $tinNhan = \App\Models\TinNhanTelegram::create([
            'ma_tin_nhan' => 'service_test_1',
            'ma_kenh' => '1',
            'noi_dung_goc' => 'test',
            'trang_thai' => 'da_xac_nhan',
        ]);
        $donHang = DonHang::create([
            'tin_nhan_telegram_id' => $tinNhan->id,
            'ma_don_noi_bo' => 'DH-SERVICE-1',
            'ten_nguoi_nhan' => 'Test',
            'so_dien_thoai' => '0900000000',
            'dia_chi' => 'Dia chi chua map',
        ]);
        $kienHang = KienHang::create([
            'don_hang_id' => $donHang->id,
            'dai' => 20,
            'rong' => 30,
            'cao' => 10,
            'can_nang' => 500,
        ]);

        $result = $this->service->tinhPhiShip($shop, $donHang, $kienHang, $donHang->dia_chi);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ma quan/huyen', $result['error']);
        $this->assertDatabaseHas('lan_tinh_phi', [
            'don_hang_id' => $donHang->id,
            'thanh_cong' => 0,
        ]);
        Http::assertNothingSent();
    }
}
