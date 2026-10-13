<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thêm cột nguon để phân biệt đơn tạo từ Telegram vs web
        // ma_tin_nhan, ma_kenh đổi nullable vì web không có mã từ Telegram
        Schema::table('tin_nhan_telegram', function (Blueprint $table): void {
            $table->string('nguon')->default('telegram')->after('trang_thai')->index();
            $table->string('ma_tin_nhan')->nullable()->unique()->change();
            $table->string('ma_kenh')->nullable()->change();
        });

        Schema::table('don_hang', function (Blueprint $table): void {
            $table->string('nguon')->default('telegram')->after('trang_thai')->index();
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table): void {
            $table->dropIndex(['nguon']);
            $table->dropColumn('nguon');
        });

        Schema::table('tin_nhan_telegram', function (Blueprint $table): void {
            $table->dropIndex(['nguon']);
            $table->dropColumn('nguon');
            // Lưu ý: không khôi phục lại NOT NULL vì có thể đã có bản ghi web
        });
    }
};
