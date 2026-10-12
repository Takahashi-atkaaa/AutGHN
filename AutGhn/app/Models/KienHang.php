<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KienHang extends Model
{
    protected $table = 'kien_hang';

    protected $fillable = [
        'don_hang_id',
        'dai',
        'rong',
        'cao',
        'can_nang',
    ];

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class);
    }

    public function getKichThuocAttribute(): string
    {
        return "{$this->dai}x{$this->rong}x{$this->cao}";
    }
}
