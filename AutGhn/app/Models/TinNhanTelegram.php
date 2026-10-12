<?php

namespace App\Models;

use App\Models\DonHang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function donHang(): HasOne
    {
        return $this->hasOne(DonHang::class);
    }

    public function layDuLieuAi(): array
    {
        return $this->du_lieu_ai ?? [];
    }

    public function layField(string $field): mixed
    {
        $duLieu = $this->layDuLieuAi();
        return $duLieu[$field] ?? null;
    }

    public function datField(string $field, mixed $value): void
    {
        $duLieu = $this->layDuLieuAi();
        $duLieu[$field] = $value;
        $this->du_lieu_ai = $duLieu;
    }

    public function batDauSuaField(string $field): void
    {
        $duLieu = $this->layDuLieuAi();
        $duLieu['_trang_thai_bot'] ??= [];
        $duLieu['_trang_thai_bot']['dang_sua_field'] = $field;
        $this->du_lieu_ai = $duLieu;
        $this->trang_thai = 'cho_sua';
    }

    public function ketThucSua(): void
    {
        $duLieu = $this->layDuLieuAi();
        unset($duLieu['_trang_thai_bot']['dang_sua_field']);
        if (empty($duLieu['_trang_thai_bot'])) {
            unset($duLieu['_trang_thai_bot']);
        }
        $this->du_lieu_ai = $duLieu ?: null;
        $this->trang_thai = 'da_phan_tich';
    }

    public function dangChoSua(): bool
    {
        return $this->trang_thai === 'cho_sua';
    }

    public function layFieldDangSua(): ?string
    {
        $duLieu = $this->layDuLieuAi();
        return $duLieu['_trang_thai_bot']['dang_sua_field'] ?? null;
    }

    public function layMaKenh(): string
    {
        return (string) $this->ma_kenh;
    }
}
