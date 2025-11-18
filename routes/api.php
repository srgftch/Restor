<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;


//Route::get('/posts', [PostController::class, 'index']);
//Route::post('/posts', [PostController::class, 'store']);
// Рестораны (для всех пользователей)
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
Route::get('/restaurants/{id}/tables', [TableController::class, 'index']);


// Столики и рестораны (только админ)

    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::put('/restaurants/{id}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{id}', [RestaurantController::class, 'destroy']);

    Route::post('/restaurants/{id}/tables', [TableController::class, 'store']);
    Route::put('/tables/{id}', [TableController::class, 'update']);
    Route::delete('/tables/{id}', [TableController::class, 'destroy']);

// Бронирования (только авторизованные пользователи)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
});

// Авторизация
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Защищенные бронирования через Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/verify-sms', [PaymentController::class, 'verifySms']);
    Route::get('/payments/result/{token}', [PaymentController::class, 'getResult']);
});





// Публичные тестовые маршруты
Route::prefix('test')->group(function () {
    Route::post('/create-test-data', function (Request $request) {
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Создаем или получаем тестового пользователя
            $user = \App\Models\User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => bcrypt('password'),
                ]
            );

            // Создаем или получаем ресторан
            $restaurant = \App\Models\Restaurant::firstOrCreate(
                ['name' => 'Test Restaurant'],
                [
                    'address' => 'Test Address',
                    'description' => 'Test restaurant for payment testing'
                ]
            );

            // Создаем или получаем стол (используем seats)
            $table = \App\Models\Table::firstOrCreate(
                ['number' => 1, 'restaurant_id' => $restaurant->id],
                ['seats' => 4]
            );

            // Создаем тестовую бронь
            $reservation = \App\Models\Reservation::create([
                'user_id' => $user->id,
                'table_id' => $table->id,
                'date_time' => now()->addDays(1)->setHour(19)->setMinute(0),
                'status' => 'pending',
            ]);

            // Создаем токен
            $token = $user->createToken('test-token')->plainTextToken;

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'reservation_id' => $reservation->id,
                'table_id' => $table->id,
                'table_number' => $table->number,
                'table_seats' => $table->seats,
                'restaurant_name' => $restaurant->name,
                'access_token' => $token,
                'message' => 'Test data created successfully'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating test data: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    });

    // Простой тестовый маршрут для проверки API
    Route::get('/status', function () {
        return response()->json([
            'status' => 'API is working',
            'timestamp' => now()->toDateTimeString(),
            'models' => [
                'users' => \App\Models\User::count(),
                'restaurants' => \App\Models\Restaurant::count(),
                'tables' => \App\Models\Table::count(),
                'reservations' => \App\Models\Reservation::count(),
            ]
        ]);
    });
});

// Защищенные маршруты
Route::middleware('auth:sanctum')->group(function () {
    // Payment routes
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/verify-sms', [PaymentController::class, 'verifySms']);
    Route::get('/payments/result/{token}', [PaymentController::class, 'getResult']);

    // Reservation routes
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);
    Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
});
