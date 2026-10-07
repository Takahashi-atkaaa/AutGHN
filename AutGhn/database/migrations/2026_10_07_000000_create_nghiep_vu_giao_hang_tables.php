<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tin_nhan_telegram', function (Blueprint $table) {
            $table->id();
            $table->string('ma_tin_nhan')->unique();
            $table->string('ma_kenh')->index();
            $table->string('ten_nguoi_gui')->nullable();
            $table->text('noi_dung_goc');
            $table->json('du_lieu_ai')->nullable();
            $table->string('trang_thai')->default('moi')->index();
            $table->timestamps();
        });

        Schema::create('shop_ghn', function (Blueprint $table) {
            $table->id();
            $table->string('ten_shop');
            $table->string('ma_shop')->unique();
            $table->text('ma_bi_mat')->nullable();
            $table->string('ten_nguoi_gui')->nullable();
            $table->string('so_dien_thoai_nguoi_gui')->nullable();
            $table->text('dia_chi_lay_hang')->nullable();
            $table->string('ma_tinh_lay_hang')->nullable();
            $table->string('ma_quan_lay_hang')->nullable();
            $table->string('ma_phuong_lay_hang')->nullable();
            $table->boolean('kich_hoat')->default(true);
            $table->timestamps();
        });

        Schema::create('don_hang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tin_nhan_telegram_id')->nullable()->constrained('tin_nhan_telegram')->nullOnDelete();
            $table->string('ma_don_noi_bo')->unique();
            $table->string('ten_nguoi_nhan');
            $table->string('so_dien_thoai');
            $table->text('dia_chi');
            $table->text('noi_dung_hang')->nullable();
            $table->decimal('tien_cod', 14, 2)->default(0);
            $table->text('ghi_chu')->nullable();
            $table->decimal('do_tin_cay_ai', 5, 2)->nullable();
            $table->string('trang_thai')->default('moi')->index();
            $table->timestamps();
        });

        Schema::create('kien_hang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('don_hang_id')->unique()->constrained('don_hang')->cascadeOnDelete();
            $table->unsignedInteger('dai');
            $table->unsignedInteger('rong');
            $table->unsignedInteger('cao');
            $table->unsignedInteger('can_nang');
            $table->timestamps();
        });

        Schema::create('lan_tinh_phi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('don_hang_id')->constrained('don_hang')->cascadeOnDelete();
            $table->foreignId('shop_ghn_id')->constrained('shop_ghn')->restrictOnDelete();
            $table->string('ma_dich_vu')->nullable();
            $table->decimal('phi_van_chuyen', 14, 2)->nullable();
            $table->decimal('phi_phu_thu', 14, 2)->nullable();
            $table->decimal('tong_phi', 14, 2)->nullable();
            $table->boolean('thanh_cong')->default(false);
            $table->text('noi_dung_loi')->nullable();
            $table->json('du_lieu_phan_hoi')->nullable();
            $table->timestamps();
        });

        Schema::create('van_don', function (Blueprint $table) {
            $table->id();
            $table->foreignId('don_hang_id')->unique()->constrained('don_hang')->cascadeOnDelete();
            $table->foreignId('shop_ghn_id')->constrained('shop_ghn')->restrictOnDelete();
            $table->string('ma_van_don')->nullable()->unique();
            $table->string('trang_thai')->default('cho_tao')->index();
            $table->text('dia_chi_phieu')->nullable();
            $table->json('du_lieu_tao')->nullable();
            $table->timestamp('thoi_gian_tao')->nullable();
            $table->timestamps();
        });

        Schema::create('lich_su_trang_thai_van_don', function (Blueprint $table) {
            $table->id();
            $table->foreignId('van_don_id')->constrained('van_don')->cascadeOnDelete();
            $table->string('ma_trang_thai_ghn')->nullable();
            $table->string('trang_thai');
            $table->text('ghi_chu')->nullable();
            $table->json('du_lieu_ghn')->nullable();
            $table->timestamp('thoi_gian_ghn')->nullable();
            $table->timestamps();
            $table->index(['van_don_id', 'trang_thai']);
        });

        Schema::create('webhook_ghn', function (Blueprint $table) {
            $table->id();
            $table->string('ma_su_kien')->nullable()->unique();
            $table->string('ma_van_don')->nullable()->index();
            $table->json('du_lieu_goc');
            $table->boolean('da_xu_ly')->default(false)->index();
            $table->text('noi_dung_loi')->nullable();
            $table->timestamp('thoi_gian_xu_ly')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_ghn');
        Schema::dropIfExists('lich_su_trang_thai_van_don');
        Schema::dropIfExists('van_don');
        Schema::dropIfExists('lan_tinh_phi');
        Schema::dropIfExists('kien_hang');
        Schema::dropIfExists('don_hang');
        Schema::dropIfExists('shop_ghn');
        Schema::dropIfExists('tin_nhan_telegram');
    }
};
