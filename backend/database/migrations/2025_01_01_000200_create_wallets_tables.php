<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 20, 2)->default(0);
            $table->decimal('locked_balance', 20, 2)->default(0)->comment('saldo tertahan transaksi berjalan');
            $table->char('currency', 3)->default('IDR');
            $table->unsignedBigInteger('version')->default(0)->comment('optimistic lock counter');
            $table->timestamps();
        });

        Schema::create('wallet_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'purchase', 'refund', 'adjustment', 'bonus', 'withdrawal']);
            $table->decimal('amount', 20, 2)->comment('positif = kredit, negatif = debit');
            $table->decimal('balance_before', 20, 2);
            $table->decimal('balance_after', 20, 2);
            $table->nullableMorphs('reference');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name', 60);
            $table->string('bank_code', 10)->nullable();
            $table->string('account_number', 40);
            $table->string('account_name', 100);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'bank_code', 'account_number'], 'bank_accounts_user_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('wallet_mutations');
        Schema::dropIfExists('wallets');
    }
};
