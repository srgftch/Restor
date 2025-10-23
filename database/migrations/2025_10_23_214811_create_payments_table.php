<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_rubles'); // сумма в копейках
            $table->string('currency', 3)->default('RUB');
            $table->string('status'); // pending, approved, declined, error
            $table->string('provider_reference')->nullable(); // ид транзакции от "банка"
            $table->string('card_brand')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->json('meta')->nullable(); // любое доп. (без CC/CVV)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
