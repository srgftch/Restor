<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Restaurant;
use App\Models\Table;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->integer('number'); // номер столика
            $table->integer('seats');  // количество мест
            $table->timestamps();
        });
        $restaurant1 = Restaurant::create([
            'name' => 'La Tavola',
            'address' => 'Via Roma 12, Milano',
            'description' => 'Современный итальянский ресторан с домашней пастой и вином.',
        ]);

        $restaurant2 = Restaurant::create([
            'name' => 'Sushi Time',
            'address' => 'Shinjuku 5-2-1, Tokyo',
            'description' => 'Аутентичные японские суши и сашими от шефа из Киото.',
        ]);

        Table::insert([
            [
                'restaurant_id' => $restaurant1->id,
                'number' => 1,
                'seats' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => $restaurant1->id,
                'number' => 2,
                'seats' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => $restaurant1->id,
                'number' => 3,
                'seats' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
