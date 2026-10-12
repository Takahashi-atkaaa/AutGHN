<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table): void {
            $table->foreignId('shop_ghn_id')
                ->nullable()
                ->after('tin_nhan_telegram_id')
                ->constrained('shop_ghn')
                ->nullOnDelete();
            $table->decimal('phi_ship', 14, 2)->nullable()->after('tien_cod');
            $table->string('dich_vu_ghn')->nullable()->after('phi_ship');
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table): void {
            $table->dropForeign(['shop_ghn_id']);
            $table->dropColumn(['shop_ghn_id', 'phi_ship', 'dich_vu_ghn']);
        });
    }
};
