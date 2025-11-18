<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    // GET /api/restaurants - список всех ресторанов (для всех)
    public function index()
    {
        $restaurants = Restaurant::with(['tables', 'tables.reservations'])
            ->withCount('tables')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($restaurants);
    }

    // GET /api/restaurants/{id} - показать один ресторан (для всех)
    public function show($id)
    {
        $restaurant = Restaurant::with(['tables', 'tables.reservations'])
            ->withCount('tables')
            ->findOrFail($id);

        return response()->json($restaurant);
    }

    // POST /api/restaurants - создание ресторана (только админ)
    public function store(Request $request)
    {
        // Проверка прав - только админ может создавать рестораны
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'description' => 'nullable|string',
        ]);

        $restaurant = Restaurant::create($data);

        return response()->json($restaurant, 201);
    }

    // PUT /api/restaurants/{id} - обновление ресторана (менеджер и админ)
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);

        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

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

    // DELETE /api/restaurants/{id} - удаление ресторана (только админ)
    public function destroy(Request $request, $id)
    {
        // Проверка прав - только админ может удалять рестораны
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $restaurant = Restaurant::findOrFail($id);

        $restaurant->tables()->each(function ($table) {
            $table->reservations()->delete();
        });
        $restaurant->tables()->delete();
        $restaurant->delete();

        return response()->json(['message' => 'Restaurant deleted successfully']);
    }

    // === TABLE MANAGEMENT (оставляем для обратной совместимости) ===

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
        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        $restaurant = Restaurant::findOrFail($restaurantId);

        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'seats' => 'required|integer|min:1|max:20',
        ]);

        // ❌ Проверяем, не занят ли номер стола
        $isTaken = Table::where('restaurant_id', $restaurantId)
            ->where('number', $data['number'])
            ->exists();

        if ($isTaken) {
            return response()->json([
                'message' => 'Table with this number already exists in this restaurant.'
            ], 409);
        }

        // ✅ Создаём стол
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
        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        $table = Table::findOrFail($id);

        $data = $request->validate([
            'number' => 'sometimes|integer|min:1',
            'seats' => 'sometimes|integer|min:1|max:20',
        ]);

        // ❌ Проверяем уникальность номера стола если он изменяется
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

        // ✅ Обновляем стол
        $table->update($data);

        return response()->json([
            'message' => 'Table updated successfully',
            'table' => $table
        ]);
    }

    // DELETE /api/tables/{id} - удаление стола
    public function deleteTable($id, Request $request)
    {
        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        $table = Table::findOrFail($id);
        $table->reservations()->delete();
        $table->delete();

        return response()->json(['message' => 'Table deleted successfully']);
    }
}
