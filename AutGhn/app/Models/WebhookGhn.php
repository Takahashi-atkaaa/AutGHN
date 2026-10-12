<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookGhn extends Model
{
    protected $table = 'webhook_ghn';

    protected $fillable = [
        'ma_su_kien',
        'ma_van_don',
        'du_lieu_goc',
        'da_xu_ly',
        'noi_dung_loi',
        'thoi_gian_xu_ly',
    ];

    protected function casts(): array
    {
        return [
            'du_lieu_goc' => 'array',
            'da_xu_ly' => 'boolean',
            'thoi_gian_xu_ly' => 'datetime',
        ];
    }
}
