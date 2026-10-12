<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KienHang;
use App\Models\LanTinhPhi;
use App\Models\ShopGhn;
use App\Models\VanDon;
use App\Services\GhnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
