<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VanDon extends Model
{
    protected $table = 'van_don';

    protected $fillable = [
        'don_hang_id',
        'shop_ghn_id',
        'ma_van_don',
        'trang_thai',
        'dia_chi_phieu',
        'du_lieu_tao',
        'thoi_gian_tao',
    ];

    protected function casts(): array
    {
        return [
            'du_lieu_tao' => 'array',
            'thoi_gian_tao' => 'datetime',
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

    public function lichSuTrangThai(): HasMany
    {
        return $this->hasMany(LichSuTrangThaiVanDon::class);
    }
}
