<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\TinNhanTelegram;
use App\Services\TaoDonHangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaoDonHangServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaoDonHangService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaoDonHangService();
    }

    public function test_tao_don_thanh_cong(): void
    {
        // Tạo tin nhắn đã phân tích
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_456',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van A',
                'phone' => '0909123456',
                'address' => '123 Duong ABC, Phuong XYZ, Quan 1, TP HCM',
                'items' => [
                    ['name' => 'Ao phong', 'quantity' => 2],
                ],
                'cod' => 350000,
                'note' => 'Giao hang gap',
            ],
        ]);

        $donHang = $this->service->taoDonHang($tinNhan);

        $this->assertInstanceOf(DonHang::class, $donHang);
        $this->assertEquals('Nguyen Van A', $donHang->ten_nguoi_nhan);
        $this->assertEquals('0909123456', $donHang->so_dien_thoai);
        $this->assertEquals('cho_kien_hang', $donHang->trang_thai);
        $this->assertStringStartsWith('DH', $donHang->ma_don_noi_bo);
        $this->assertEquals(350000, $donHang->tien_cod);
    }

    public function test_chong_tao_trung(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_457',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van B',
                'phone' => '0909123457',
                'address' => '456 Duong XYZ, Phuong ABC, Quan 2, TP HCM',
            ],
        ]);

        // Tạo lần đầu - thành công
        $donHang1 = $this->service->taoDonHang($tinNhan);
        $this->assertInstanceOf(DonHang::class, $donHang1);

        // Tạo lần 2 - phải throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Da ton tai don hang cho tin nhan nay.');
        $this->service->taoDonHang($tinNhan);
    }

    public function test_thieu_du_lieu_bat_buoc(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_458',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => '', // Thiếu tên
                'phone' => '0909123458',
                'address' => '789 Duong DEF, Phuong GHI, Quan 3, TP HCM',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thieu ten nguoi nhan.');
        $this->service->taoDonHang($tinNhan);
    }

    public function test_sdt_khong_hop_le(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_459',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van C',
                'phone' => '123', // SDT quá ngắn
                'address' => '999 Duong JKL, Phuong MNO, Quan 4, TP HCM',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('So dien thoai khong hop le.');
        $this->service->taoDonHang($tinNhan);
    }

    public function test_trang_thai_chua_phan_tich(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_460',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'moi', // Chưa phân tích
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van D',
                'phone' => '0909123460',
                'address' => '111 Duong PQR, Phuong STU, Quan 5, TP HCM',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tin nhan chua duoc phan tich hoac da xu ly.');
        $this->service->taoDonHang($tinNhan);
    }

    public function test_dia_chi_qua_ngan(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_461',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van E',
                'phone' => '0909123461',
                'address' => 'abc', // Địa chỉ quá ngắn
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dia chi khong hop le hoac thieu.');
        $this->service->taoDonHang($tinNhan);
    }

    public function test_ma_don_noi_bo_format(): void
    {
        $tinNhan = TinNhanTelegram::create([
            'ma_tin_nhan' => '123_462',
            'ma_kenh' => '123',
            'ten_nguoi_gui' => 'Test User',
            'noi_dung_goc' => 'Test order',
            'trang_thai' => 'da_phan_tich',
            'du_lieu_ai' => [
                'customer_name' => 'Nguyen Van F',
                'phone' => '0909123462',
                'address' => '222 Duong VWX, Phuong YZ, Quan 6, TP HCM',
            ],
        ]);

        $donHang = $this->service->taoDonHang($tinNhan);

        // Format: DH-YYYYMMDD-NNNN
        $this->assertMatchesRegularExpression('/^DH\d{8}-\d{4}$/', $donHang->ma_don_noi_bo);
    }
}
