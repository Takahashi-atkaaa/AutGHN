<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LichSuTrangThaiVanDon extends Model
{
    protected $table = 'lich_su_trang_thai_van_don';

    protected $fillable = [
        'van_don_id',
        'ma_trang_thai_ghn',
        'trang_thai',
        'ghi_chu',
        'du_lieu_ghn',
        'thoi_gian_ghn',
    ];

    protected function casts(): array
    {
        return [
            'du_lieu_ghn' => 'array',
            'thoi_gian_ghn' => 'datetime',
        ];
    }

    public function vanDon(): BelongsTo
    {
        return $this->belongsTo(VanDon::class);
    }
}
