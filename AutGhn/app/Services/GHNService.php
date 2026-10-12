<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\KienHang;
use App\Models\LanTinhPhi;
use App\Models\ShopGhn;
use App\Models\VanDon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhnService
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.ghn.token');
        $this->baseUrl = config('services.ghn.base_url');
    }

    /**
     * Chuẩn hóa địa chỉ qua GHN API để lấy district_id và ward_code.
     *
     * @param string $diaChi Địa chỉ cần chuẩn hóa
     * @return array ['district_id' => int, 'ward_code' => string]
     */
    public function chuanHoaDiaChi(string $diaChi): array
    {
        // Gọi API để lấy thông tin tỉnh/quận/phường
        // GHN có API /v2/district và /ward/list
        // Tạm thời trả về mảng rỗng, cần implement khi có API key thực
        return [
            'district_id' => null,
            'ward_code' => null,
        ];
    }

    /**
     * Tính phí ship cho đơn hàng.
     * Lưu kết quả vào bảng lan_tinh_phi.
     *
     * @param ShopGhn $shop Shop GHN
     * @param DonHang $donHang Đơn hàng (bắt buộc, dùng để lưu lan_tinh_phi)
     * @param KienHang $kienHang Thông tin kiện hàng
     * @param string $diaChiNhan Địa chỉ nhận
     * @param int $cod Số tiền COD
     * @return array Kết quả tính phí
     */
    public function tinhPhiShip(ShopGhn $shop, DonHang $donHang, KienHang $kienHang, string $diaChiNhan, int $cod = 0): array
    {
        // Chuẩn hóa địa chỉ
        $diaChiChuan = $this->chuanHoaDiaChi($diaChiNhan);

        $payload = [
            'shop_id' => (int) $shop->ma_shop,
            'from_district' => (int) ($shop->ma_quan_lay_hang ?? 0),
            'to_district' => (int) ($diaChiChuan['district_id'] ?? 0),
            'to_ward_code' => $diaChiChuan['ward_code'] ?? '',
            'height' => (int) $kienHang->cao,
            'length' => (int) $kienHang->dai,
            'weight' => (int) $kienHang->can_nang,
            'insurance_value' => $cod,
            'service_type_id' => 2, // 2 = Express, 1 = Standard
        ];

        try {
            $response = Http::withHeaders([
                'Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/v2/shipping-order/available-services", $payload);

            $data = $response->json();

            // Lưu vào lan_tinh_phi
            $lanTinhPhi = $this->luuLanTinhPhi(
                $donHang,
                $shop,
                $payload,
                $data,
                $response->successful() && !empty($data['data']),
                $response->successful() ? null : ($data['message'] ?? 'Loi khong xac dinh')
            );

            if ($response->successful() && !empty($data['data'])) {
                return [
                    'success' => true,
                    'services' => $data['data'],
                    'lan_tinh_phi_id' => $lanTinhPhi->id,
                ];
            }

            Log::warning('GHN tinh phi that bai', [
                'don_hang_id' => $donHang->id,
                'response' => $data,
            ]);
            return [
                'success' => false,
                'error' => $data['message'] ?? 'Khong lay duoc phi ship',
                'lan_tinh_phi_id' => $lanTinhPhi->id,
            ];
        } catch (\Exception $e) {
            Log::error('GHN tinh phi loi', [
                'don_hang_id' => $donHang->id,
                'error' => $e->getMessage(),
            ]);

            $this->luuLanTinhPhi(
                $donHang,
                $shop,
                $payload,
                ['error' => $e->getMessage()],
                false,
                $e->getMessage()
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tạo vận đơn GHN.
     * Chống tạo trùng bằng cách kiểm tra van_don đã tồn tại.
     *
     * @param ShopGhn $shop Shop GHN
     * @param DonHang $donHang Đơn hàng
     * @param KienHang $kienHang Kiện hàng
     * @return array Kết quả tạo vận đơn
     */
    public function taoVanDon(ShopGhn $shop, DonHang $donHang, KienHang $kienHang): array
    {
        // Chống tạo trùng
        $vanDonCu = VanDon::where('don_hang_id', $donHang->id)
            ->whereNotNull('ma_van_don')
            ->first();

        if ($vanDonCu) {
            return [
                'success' => false,
                'error' => 'Da co van don: ' . $vanDonCu->ma_van_don,
                'order_code' => $vanDonCu->ma_van_don,
            ];
        }

        // Chuẩn hóa địa chỉ
        $diaChiChuan = $this->chuanHoaDiaChi($donHang->dia_chi);

        // Lấy service_id từ lần tính phí gần nhất
        $lanTinhPhi = LanTinhPhi::where('don_hang_id', $donHang->id)
            ->where('thanh_cong', true)
            ->orderBy('id', 'desc')
            ->first();

        $serviceId = 2; // Default: Express
        if ($lanTinhPhi && !empty($lanTinhPhi->du_lieu_phan_hoi)) {
            $data = $lanTinhPhi->du_lieu_phan_hoi;
            if (!empty($data['data'][0]['service_id'])) {
                $serviceId = (int) $data['data'][0]['service_id'];
            }
        }

        $payload = [
            'shop_id' => (int) $shop->ma_shop,
            'from_name' => $shop->ten_nguoi_gui,
            'from_phone' => $shop->so_dien_thoai_nguoi_gui,
            'from_address' => $shop->dia_chi_lay_hang,
            'from_ward_code' => $shop->ma_phuong_lay_hang,
            'from_district_id' => (int) $shop->ma_quan_lay_hang,
            'to_name' => $donHang->ten_nguoi_nhan,
            'to_phone' => $donHang->so_dien_thoai,
            'to_address' => $donHang->dia_chi,
            'to_ward_code' => $diaChiChuan['ward_code'] ?? '',
            'to_district_id' => (int) ($diaChiChuan['district_id'] ?? 0),
            'service_id' => $serviceId,
            'weight' => (int) $kienHang->can_nang,
            'length' => (int) $kienHang->dai,
            'width' => (int) $kienHang->rong,
            'height' => (int) $kienHang->cao,
            'cod_amount' => (int) $donHang->tien_cod,
            'content' => $donHang->noi_dung_hang,
            'note' => $donHang->ghi_chu ?? '',
            'required_note' => 'CHOTHUHANG',
        ];

        try {
            $response = Http::withHeaders([
                'Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$this->baseUrl}/v2/shipping-order/create", $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['data']['order_code'])) {
                return [
                    'success' => true,
                    'order_code' => $data['data']['order_code'],
                    'data' => $data['data'],
                ];
            }

            $error = $data['message'] ?? 'Tao van don that bai';
            Log::warning('GHN tao van don that bai', [
                'don_hang_id' => $donHang->id,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'error' => $error,
            ];
        } catch (\Exception $e) {
            Log::error('GHN tao van don loi', [
                'don_hang_id' => $donHang->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lấy phiếu in từ GHN.
     *
     * @param string $orderCode Mã vận đơn GHN
     * @return array ['loai' => 'url'|'file', 'du_lieu' => string]
     */
    public function layPhieuIn(string $orderCode): array
    {
        try {
            $response = Http::withHeaders([
                'Token' => $this->token,
            ])->timeout(10)->get("{$this->baseUrl}/v2/a5/gen-token", [
                'order_codes' => $orderCode,
            ]);

            $data = $response->json();

            if ($response->successful() && !empty($data['data']['token'])) {
                return [
                    'loai' => 'url',
                    'du_lieu' => "https://dev.print.ghn.vn/a5/{$data['data']['token']}",
                ];
            }

            return [
                'loai' => 'error',
                'du_lieu' => $data['message'] ?? 'Khong lay duoc phieu in',
            ];
        } catch (\Exception $e) {
            return [
                'loai' => 'error',
                'du_lieu' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lưu kết quả tính phí vào bảng lan_tinh_phi.
     *
     * @param DonHang|null $donHang Đơn hàng (null nếu chưa có)
     * @param ShopGhn $shop Shop GHN
     * @param array $request Request gửi đi
     * @param array $response Response nhận về
     * @param bool $thanhCong Có thành công không
     * @param string|null $loi Nội dung lỗi
     * @return LanTinhPhi Bản ghi đã lưu
     */
    private function luuLanTinhPhi(
        ?DonHang $donHang,
        ShopGhn $shop,
        array $request,
        array $response,
        bool $thanhCong,
        ?string $loi = null
    ): LanTinhPhi {
        // Trích xuất phí từ response
        $phiVanChuyen = null;
        $phiPhuThu = 0;
        $tongPhi = null;

        if (!empty($response['data'][0])) {
            $service = $response['data'][0];
            $phiVanChuyen = $service['fee'] ?? null;
            $phiPhuThu = $service['insurance_fee'] ?? 0;
            $tongPhi = $service['total_fee'] ?? $phiVanChuyen;
        }

        return LanTinhPhi::create([
            'don_hang_id' => $donHang?->id,
            'shop_ghn_id' => $shop->id,
            'phi_van_chuyen' => $phiVanChuyen,
            'phi_phu_thu' => $phiPhuThu,
            'tong_phi' => $tongPhi,
            'thanh_cong' => $thanhCong,
            'noi_dung_loi' => $loi,
            'du_lieu_phan_hoi' => $response,
        ]);
    }
}
