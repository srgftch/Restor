<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function store(Request $request, $restaurantId)
    {
        $table = Table::create([
            'restaurant_id' => $restaurantId,
            'number' => $request->number,
            'seats' => $request->seats,
        ]);
        return response()->json($table, 201);
    }

    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);
        $table->update($request->only('number', 'seats'));
        return response()->json($table);
    }

    public function destroy($id)
    {
        $table = Table::findOrFail($id);
        $table->delete();
        return response()->noContent();
    }
}

