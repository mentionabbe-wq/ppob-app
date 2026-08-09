<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->string('ref_id', 64)->unique()->comment('idempotency key');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('promo_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name', 120)->comment('snapshot nama produk saat transaksi');
            $table->string('customer_no', 40);
            $table->string('customer_name', 120)->nullable();

            $table->decimal('base_price', 20, 2)->comment('harga modal');
            $table->decimal('sell_price', 20, 2)->comment('harga jual');
            $table->decimal('admin_fee', 20, 2)->default(0);
            $table->decimal('discount', 20, 2)->default(0);
            $table->decimal('total_paid', 20, 2)->comment('yang dibayar user');
            $table->decimal('profit', 20, 2)->default(0);

            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'refunded', 'canceled'])
                ->default('pending');
            $table->string('serial_number', 191)->nullable()->comment('SN / token PLN');
            $table->string('provider_ref', 100)->nullable();
            $table->text('provider_message')->nullable();
            $table->json('meta')->nullable()->comment('detail tagihan pascabayar, dsb');

            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['customer_no']);
            $table->index(['created_at']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 2)->comment('nominal yang diminta');
            $table->unsignedSmallInteger('unique_code')->default(0)->comment('kode unik transfer bank');
            $table->decimal('total_amount', 20, 2)->comment('amount + unique_code / total tagihan gateway');
            $table->enum('method', ['bank_transfer', 'virtual_account', 'qris', 'ewallet', 'manual']);
            $table->string('channel', 40)->nullable()->comment('bca|bni|ovo|dana|...');
            $table->string('va_number', 40)->nullable();
            $table->text('qris_payload')->nullable();
            $table->string('payment_ref', 100)->nullable()->comment('order id di payment gateway');
            $table->string('proof_path', 191)->nullable();
            $table->enum('status', ['pending', 'waiting_payment', 'paid', 'approved', 'rejected', 'expired'])
                ->default('pending');
            $table->text('note')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'expired_at']);
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10)->default('outgoing')->comment('outgoing|incoming');
            $table->string('endpoint', 191);
            $table->string('method', 10)->default('POST');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
            $table->index(['transaction_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('null = broadcast ke semua user');
            $table->string('type', 40)->default('system')->comment('transaction|deposit|promo|system');
            $table->string('title', 150);
            $table->text('body');
            $table->string('image_path', 191)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('transactions');
    }
};
