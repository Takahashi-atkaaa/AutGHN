<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanTinhPhi extends Model
{
    protected $table = 'lan_tinh_phi';

    protected $fillable = [
        'don_hang_id',
        'shop_ghn_id',
        'ma_dich_vu',
        'phi_van_chuyen',
        'phi_phu_thu',
        'tong_phi',
        'thanh_cong',
        'noi_dung_loi',
        'du_lieu_phan_hoi',
    ];

    protected function casts(): array
    {
        return [
            'phi_van_chuyen' => 'decimal:2',
            'phi_phu_thu' => 'decimal:2',
            'tong_phi' => 'decimal:2',
            'thanh_cong' => 'boolean',
            'du_lieu_phan_hoi' => 'array',
        ];
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class);
    }

    public function shopGhn(): BelongsTo
    {
        return $this->belongsTo(ShopGhn::class);
    }
}
