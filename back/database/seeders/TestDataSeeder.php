<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Table;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // тест ресторан
        $restaurant = Restaurant::firstOrCreate(
            ['name' => 'Test Restaurant'],
            [
                'address' => 'Test Address',
                'description' => 'Test restaurant for payment testing'
            ]
        );

        // тест столы
        Table::firstOrCreate(
            ['number' => 1, 'restaurant_id' => $restaurant->id],
            ['seats' => 4]
        );

        Table::firstOrCreate(
            ['number' => 2, 'restaurant_id' => $restaurant->id],
            ['seats' => 2]
        );

        // тест юзер
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $this->command->info('Test data created successfully');
        $this->command->info("Restaurant: {$restaurant->name}");
        $this->command->info("Tables created: 2");
        $this->command->info("User: test@example.com / password");
    }
}
