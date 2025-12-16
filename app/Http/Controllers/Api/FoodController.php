<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodController extends Controller
{
    public function index()
    {
        return Food::orderBy('name')->get();
    }

    public function show($id)
    {
        return Food::findOrFail($id);
    }

    public function store(Request $request)
    {
        if (!$this->canManageFoods()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->validateData($request);

        $food = Food::create($data);

        return response()->json($food, 201);
    }

    public function update(Request $request, $id)
    {
        if (!$this->canManageFoods()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $food = Food::findOrFail($id);
        $data = $this->validateData($request, true);

        $food->update($data);

        return response()->json($food);
    }

    public function destroy($id)
    {
        if (!$this->canManageFoods()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $food = Food::findOrFail($id);
        $food->delete();

        return response()->json(['message' => 'Food deleted successfully']);
    }

    private function validateData(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255',
            'image_url' => 'sometimes|nullable|string|max:500',
            'calories' => 'sometimes|nullable|integer|min:0',
            'ingredients' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
        ];

        return $request->validate($rules);
    }

    private function canManageFoods(): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isManager());
    }
}

