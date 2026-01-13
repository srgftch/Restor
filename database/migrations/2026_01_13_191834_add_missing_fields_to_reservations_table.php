<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('duration')->default(2)->after('date_time');
            $table->datetime('end_time')->nullable()->after('duration');
            $table->string('user_name')->nullable()->after('user_id');
            $table->string('user_phone')->nullable()->after('user_name');
            $table->integer('guests_count')->default(1)->after('user_phone');
            $table->text('special_requests')->nullable()->after('guests_count');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['duration', 'end_time', 'user_name', 'user_phone', 'guests_count', 'special_requests']);
        });
    }
};
