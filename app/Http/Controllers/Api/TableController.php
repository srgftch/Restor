<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    // GET /api/restaurants/{restaurantId}/tables - список столов ресторана (для всех)
    public function index($restaurantId)
    {
        $tables = Table::where('restaurant_id', $restaurantId)
            ->with(['reservations'])
            ->orderBy('number')
            ->get();

        return response()->json($tables);
    }

    // POST /api/restaurants/{restaurantId}/tables - создание стола (менеджер и админ)
    public function store(Request $request, $restaurantId)
    {
        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'seats' => 'required|integer|min:1|max:20',
        ]);

        // Проверяем уникальность номера стола
        $exists = Table::where('restaurant_id', $restaurantId)
            ->where('number', $data['number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Table with this number already exists in this restaurant.'
            ], 409);
        }

        $table = Table::create([
            'restaurant_id' => $restaurantId,
            'number' => $data['number'],
            'seats' => $data['seats'],
        ]);

        return response()->json($table, 201);
    }

    // PUT /api/tables/{id} - обновление стола (менеджер и админ)
    public function update(Request $request, $id)
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

        // Проверяем уникальность номера стола если он изменяется
        if (isset($data['number']) && $data['number'] != $table->number) {
            $exists = Table::where('restaurant_id', $table->restaurant_id)
                ->where('number', $data['number'])
                ->where('id', '!=', $table->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Table with this number already exists in this restaurant.'
                ], 409);
            }
        }

        $table->update($data);
        return response()->json($table);
    }

    // DELETE /api/tables/{id} - удаление стола (менеджер и админ)
    public function destroy(Request $request, $id)
    {
        // Проверка прав
        if (!$request->user()->isAdmin() && !$request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        $table = Table::findOrFail($id);
        $table->reservations()->delete();
        $table->delete();
        return response()->noContent();
    }
    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'date_time' => 'required|date',
        ]);

        $isTaken = Reservation::where('table_id', $data['table_id'])
            ->where('date_time', $data['date_time'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        return response()->json([
            'available' => !$isTaken,
            'message' => $isTaken ? 'Стол занят на это время' : 'Стол свободен'
        ]);
    }
}

