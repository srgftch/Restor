<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index()
    {
        return Restaurant::with('tables')->get();
    }

    public function show($id)
    {
        return Restaurant::with('tables')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $restaurant = Restaurant::create($request->only('name', 'address', 'description'));
        return response()->json($restaurant, 201);
    }

    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->update($request->only('name', 'address', 'description'));
        return response()->json($restaurant);
    }

    public function destroy($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->delete();
        return response()->noContent();
    }
}

