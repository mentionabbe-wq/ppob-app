<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('icon', 120)->nullable()->comment('nama ikon atau path gambar');
            $table->string('color', 9)->nullable();
            $table->enum('type', ['prepaid', 'postpaid'])->default('prepaid');
            $table->string('input_label', 60)->default('Nomor Tujuan');
            $table->string('input_type', 20)->default('phone')->comment('phone|number|text|email');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('code', 30)->unique()->comment('digiflazz|vipreseller|...');
            $table->string('base_url', 191);
            $table->text('credentials_encrypted')->nullable()->comment('JSON terenkripsi');
            $table->decimal('balance', 20, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100)->comment('kecil = diprioritaskan');
            $table->timestamp('balance_synced_at')->nullable();
            $table->timestamp('products_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->string('sku', 60)->unique()->comment('SKU internal, stabil lintas provider');
            $table->string('provider_sku', 60)->comment('kode produk di sisi provider');
            $table->string('name', 120);
            $table->string('brand', 60)->nullable()->comment('TELKOMSEL, PLN, dst');
            $table->string('type', 60)->nullable()->comment('Umum, Data, dst');
            $table->decimal('base_price', 20, 2)->comment('harga modal dari provider');
            $table->decimal('sell_price', 20, 2)->comment('harga jual ke user');
            $table->enum('margin_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('margin_value', 20, 2)->default(0);
            $table->decimal('admin_fee', 20, 2)->default(0)->comment('biaya admin produk pascabayar');
            $table->boolean('is_active')->default(true)->comment('dikelola admin');
            $table->boolean('is_available')->default(true)->comment('status stok dari provider');
            $table->boolean('is_featured')->default(false);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider_id', 'provider_sku']);
            $table->index(['category_id', 'is_active', 'is_available']);
            $table->index(['brand', 'is_active']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('image_path', 191);
            $table->string('action_type', 20)->default('none')->comment('none|url|category|product|promo');
            $table->string('action_value', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('image_path', 191)->nullable();
            $table->enum('discount_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('discount_value', 20, 2);
            $table->decimal('max_discount', 20, 2)->nullable();
            $table->decimal('min_transaction', 20, 2)->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quota')->nullable()->comment('null = tanpa batas');
            $table->unsignedInteger('used')->default(0);
            $table->unsignedSmallInteger('per_user_limit')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->string('type', 20)->default('string')->comment('string|int|bool|json');
            $table->string('label', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('products');
        Schema::dropIfExists('providers');
        Schema::dropIfExists('categories');
    }
};
