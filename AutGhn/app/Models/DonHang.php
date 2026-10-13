<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DonHang extends Model
{
    protected $table = 'don_hang';

    protected $fillable = [
        'tin_nhan_telegram_id',
        'ma_don_noi_bo',
        'ten_nguoi_nhan',
        'so_dien_thoai',
        'dia_chi',
        'noi_dung_hang',
        'tien_cod',
        'phi_ship',
        'dich_vu_ghn',
        'shop_ghn_id',
        'ghi_chu',
        'do_tin_cay_ai',
        'trang_thai',
        'nguon',
    ];

    protected function casts(): array
    {
        return [
            'tien_cod' => 'decimal:2',
            'do_tin_cay_ai' => 'decimal:2',
            'phi_ship' => 'decimal:2',
        ];
    }

    public function tinNhanTelegram(): BelongsTo
    {
        return $this->belongsTo(TinNhanTelegram::class);
    }

    public function kienHang(): HasOne
    {
        return $this->hasOne(KienHang::class);
    }

    public function vanDon(): HasOne
    {
        return $this->hasOne(VanDon::class);
    }

    public function lanTinhPhis(): HasMany
    {
        return $this->hasMany(LanTinhPhi::class);
    }

    public function shopGhn(): BelongsTo
    {
        return $this->belongsTo(ShopGhn::class);
    }
}
