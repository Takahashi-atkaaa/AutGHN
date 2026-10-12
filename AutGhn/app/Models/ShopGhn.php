<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopGhn extends Model
{
    protected $table = 'shop_ghn';

    protected $fillable = [
        'ten_shop',
        'ma_shop',
        'ma_bi_mat',
        'ten_nguoi_gui',
        'so_dien_thoai_nguoi_gui',
        'dia_chi_lay_hang',
        'ma_tinh_lay_hang',
        'ma_quan_lay_hang',
        'ma_phuong_lay_hang',
        'kich_hoat',
    ];

    protected function casts(): array
    {
        return [
            'kich_hoat' => 'boolean',
        ];
    }

    public function vanDons(): HasMany
    {
        return $this->hasMany(VanDon::class);
    }

    public function lanTinhPhis(): HasMany
    {
        return $this->hasMany(LanTinhPhi::class);
    }

    public function scopeKichHoat($query)
    {
        return $query->where('kich_hoat', true);
    }
}
