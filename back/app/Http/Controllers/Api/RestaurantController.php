<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestaurantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // GET /api/restaurants - список всех ресторанов
    public function index(Request $request)
    {
        $restaurants = Restaurant::with(['tables', 'tables.reservations'])
            ->withCount('tables')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($restaurants);
    }

    // POST /api/restaurants - создание ресторана
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'description' => 'nullable|string',
        ]);

        $restaurant = DB::transaction(function () use ($data) {
            return Restaurant::create($data);
        });

        return response()->json($restaurant, 201);
    }

    // GET /api/restaurants/{id} - показать один ресторан
    public function show($id, Request $request)
    {
        $restaurant = Restaurant::with(['tables', 'tables.reservations'])
            ->withCount('tables')
            ->findOrFail($id);

        return response()->json($restaurant);
    }

    // PUT /api/restaurants/{id} - обновление ресторана
    public function update($id, Request $request)
    {
        $restaurant = Restaurant::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
        ]);

        $restaurant->update($data);

        return response()->json([
            'message' => 'Restaurant updated successfully',
            'restaurant' => $restaurant
        ]);
    }

    // DELETE /api/restaurants/{id} - удаление ресторана
    public function destroy($id, Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $restaurant = Restaurant::findOrFail($id);

        DB::transaction(function () use ($restaurant) {
            $restaurant->tables()->each(function ($table) {
                $table->reservations()->delete();
            });
            $restaurant->tables()->delete();
            $restaurant->delete();
        });

        return response()->json(['message' => 'Restaurant deleted successfully']);
    }

    // GET /api/restaurants/{restaurantId}/tables - список столов ресторана
    public function getTables($restaurantId, Request $request)
    {
        $tables = Table::where('restaurant_id', $restaurantId)
            ->with(['reservations'])
            ->orderBy('number')
            ->get();

        return response()->json($tables);
    }

    // POST /api/restaurants/{restaurantId}/tables - создание стола
    public function createTable($restaurantId, Request $request)
    {
        $restaurant = Restaurant::findOrFail($restaurantId);

        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'seats' => 'required|integer|min:1|max:20',
        ]);

        // Проверка номера стола
        $isTaken = Table::where('restaurant_id', $restaurantId)
            ->where('number', $data['number'])
            ->exists();

        if ($isTaken) {
            return response()->json([
                'message' => 'Table with this number already exists in this restaurant.'
            ], 409);
        }

        // Создание стола
        $table = Table::create([
            'restaurant_id' => $restaurantId,
            'number' => $data['number'],
            'seats' => $data['seats'],
        ]);

        return response()->json($table, 201);
    }

    // PUT /api/tables/{id} - обновление стола
    public function updateTable($id, Request $request)
    {
        $table = Table::findOrFail($id);

        $data = $request->validate([
            'number' => 'sometimes|integer|min:1',
            'seats' => 'sometimes|integer|min:1|max:20',
        ]);

        // Проверка уникальности номера стола при изменении
        if (isset($data['number']) && $data['number'] != $table->number) {
            $isTaken = Table::where('restaurant_id', $table->restaurant_id)
                ->where('number', $data['number'])
                ->where('id', '!=', $table->id)
                ->exists();

            if ($isTaken) {
                return response()->json([
                    'message' => 'Table with this number already exists in this restaurant.'
                ], 409);
            }
        }

        // Обновление стола
        $table->update($data);

        return response()->json([
            'message' => 'Table updated successfully',
            'table' => $table
        ]);
    }

    // DELETE /api/tables/{id} - удаление стола
    public function deleteTable($id, Request $request)
    {
        $table = Table::findOrFail($id);
        $table->reservations()->delete();
        $table->delete();

        return response()->json(['message' => 'Table deleted successfully']);
    }
}
