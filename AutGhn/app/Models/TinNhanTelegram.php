<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinNhanTelegram extends Model
{
    protected $table = 'tin_nhan_telegram';

    protected $fillable = [
        'ma_tin_nhan',
        'ma_kenh',
        'ten_nguoi_gui',
        'noi_dung_goc',
        'du_lieu_ai',
        'trang_thai',
    ];

    protected function casts(): array
    {
        return ['du_lieu_ai' => 'array'];
    }
}
