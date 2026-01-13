<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    // GET /api/reservations - список бронирований
    public function index(Request $request)
    {
        try {
            $query = Reservation::with(['user', 'table', 'foods']);

            if ($request->filled('restaurant_id')) {
                $query->whereHas('table', function ($q) use ($request) {
                    $q->where('restaurant_id', $request->restaurant_id);
                });
            }

            if ($request->filled('date')) {
                $query->whereDate('date_time', $request->date);
            }

            $user = Auth::user();
            if ($user && !$user->isAdmin() && !$user->isManager()) {
                $query->where('user_id', $user->id);
            }

            $reservations = $query->orderBy('date_time', 'desc')->get();

            return response()->json($reservations);
        } catch (\Throwable $e) {
            Log::error('Reservation index error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to load reservations',
            ], 500);
        }
    }

    // POST /api/reservations - создание бронирования
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'table_id' => 'required|exists:tables,id',
                'date_time' => 'required|date|after:now',
                'price' => 'required|numeric|min:0',
                'foods' => 'sometimes|array',
                'foods.*.food_id' => 'required_with:foods|exists:foods,id',
                'foods.*.quantity' => 'sometimes|integer|min:1|max:20',
                // НОВЫЕ ПОЛЯ
                'duration' => 'nullable|integer|min:1|max:8',
                'user_name' => 'nullable|string|max:255',
                'user_phone' => 'nullable|string|max:20',
                'guests_count' => 'nullable|integer|min:1|max:20',
                'special_requests' => 'nullable|string',
            ]);

            $dateTime = $this->normalizeDateTime($validated['date_time']);
            $duration = $validated['duration'] ?? 2;
            $foodsTotal = $this->calculateFoodsTotal($validated['foods'] ?? []);
            $price = max($validated['price'], $foodsTotal);

            // ВРЕМЯ КОНЕЧНОЕ
            $endTime = Carbon::parse($dateTime)->addHours($duration);

            // Проверяем наличие бронирований на пересекающиеся интервалы
            $existingReservation = Reservation::where('table_id', $validated['table_id'])
                ->where(function ($query) use ($dateTime, $endTime) {
                    // Проверяем пересечение интервалов
                    $query->where(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь начинается во время существующей
                        $q->where('date_time', '<=', $dateTime)
                            ->where('end_time', '>', $dateTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь заканчивается во время существующей
                        $q->where('date_time', '<', $endTime)
                            ->where('end_time', '>=', $endTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь полностью внутри существующей
                        $q->where('date_time', '>=', $dateTime)
                            ->where('end_time', '<=', $endTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Существующая бронь полностью внутри новой
                        $q->where('date_time', '<=', $dateTime)
                            ->where('end_time', '>=', $endTime);
                    });
                })
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingReservation) {
                return response()->json([
                    'message' => 'Стол уже забронирован на это время',
                ], 409);
            }

            $user = Auth::user();

            $reservation = Reservation::create([
                'user_id' => $user?->id,
                'table_id' => $validated['table_id'],
                'date_time' => $dateTime,
                'duration' => $duration,
                'end_time' => $endTime,
                'status' => 'pending',
                'price' => $price,
                'user_name' => $validated['user_name'] ?? $user?->name,
                'user_phone' => $validated['user_phone'] ?? '',
                'guests_count' => $validated['guests_count'] ?? 1,
                'special_requests' => $validated['special_requests'] ?? '',
            ]);

            if (!empty($validated['foods'])) {
                $reservation->foods()->attach($this->mapFoods($validated['foods']));
            }

            return response()->json($reservation, 201);
        } catch (\Throwable $e) {
            Log::error('Reservation store error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to create reservation',
            ], 500);
        }
    }

    // GET /api/reservations/{id} - показать одну бронь
    public function show($id)
    {
        try {
            $reservation = Reservation::with(['user', 'table', 'foods'])->findOrFail($id);

            $user = Auth::user();
            if ($user && !$user->isAdmin() && !$user->isManager() && $reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            return response()->json($reservation);
        } catch (\Throwable $e) {
            Log::warning('Reservation show error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Not found',
                'message' => 'Reservation not found',
            ], 404);
        }
    }

    // DELETE /api/reservations/{id} - удаление брони
    public function destroy($id)
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (!$user->isAdmin() && !$user->isManager() && $reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $reservation->delete();

            return response()->json(['message' => 'Reservation deleted successfully']);
        } catch (\Throwable $e) {
            Log::warning('Reservation destroy error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Not found',
                'message' => 'Reservation not found',
            ], 404);
        }
    }

    // PUT /api/reservations/{id} - обновление брони (для менеджеров/админов)
    public function update(Request $request, $id)
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $user = Auth::user();
            if (!$user || (!$user->isAdmin() && !$user->isManager())) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $validated = $request->validate([
                'status' => 'sometimes|in:pending,confirmed,canceled',
                'price' => 'sometimes|numeric|min:0',
                'foods' => 'sometimes|array',
                'foods.*.food_id' => 'required_with:foods|exists:foods,id',
                'foods.*.quantity' => 'sometimes|integer|min:1|max:20',
            ]);

            $reservation->update($validated);
            if (array_key_exists('foods', $validated)) {
                $reservation->foods()->sync($this->mapFoods($validated['foods']));
            }

            $currentFoods = array_key_exists('foods', $validated)
                ? $validated['foods']
                : $reservation->foods->map(fn ($food) => [
                    'food_id' => $food->id,
                    'quantity' => $food->pivot->quantity,
                ])->toArray();

            $foodsTotal = $this->calculateFoodsTotal($currentFoods);
            $deposit = $validated['price'] ?? $reservation->price ?? 0;
            $reservation->update(['price' => max($deposit, $foodsTotal)]);

            return response()->json([
                'message' => 'Reservation updated successfully',
                'reservation' => $reservation,
            ]);
        } catch (\Throwable $e) {
            Log::error('Reservation update error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to update reservation',
            ], 500);
        }
    }

    // POST /api/reservations/check-availability - проверка доступности стола
    public function checkAvailability(Request $request)
    {
        try {
            $validated = $request->validate([
                'table_id' => 'required|exists:tables,id',
                'date_time' => 'required|date|after:now',
                'duration' => 'nullable|integer|min:1|max:8',
                'reservation_id' => 'nullable|exists:reservations,id',
            ]);

            $dateTime = $this->normalizeDateTime($validated['date_time']);
            $duration = $validated['duration'] ?? 2;
            $endTime = Carbon::parse($dateTime)->addHours($duration);

            $query = Reservation::where('table_id', $validated['table_id'])
                ->where(function ($query) use ($dateTime, $endTime) {
                    // Проверяем пересечение интервалов
                    $query->where(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь начинается во время существующей
                        $q->where('date_time', '<=', $dateTime)
                            ->where('end_time', '>', $dateTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь заканчивается во время существующей
                        $q->where('date_time', '<', $endTime)
                            ->where('end_time', '>=', $endTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Новая бронь полностью внутри существующей
                        $q->where('date_time', '>=', $dateTime)
                            ->where('end_time', '<=', $endTime);
                    })->orWhere(function ($q) use ($dateTime, $endTime) {
                        // Существующая бронь полностью внутри новой
                        $q->where('date_time', '<=', $dateTime)
                            ->where('end_time', '>=', $endTime);
                    });
                })
                ->whereIn('status', ['pending', 'confirmed']);

            if (!empty($validated['reservation_id'])) {
                $query->where('id', '!=', $validated['reservation_id']);
            }

            $existingReservation = $query->first();

            return response()->json([
                'available' => !$existingReservation,
                'existing_reservation' => $existingReservation,
            ]);
        } catch (\Throwable $e) {
            Log::error('Reservation availability error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'Failed to check availability',
            ], 500);
        }
    }

    private function normalizeDateTime(string $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function mapFoods(array $foods): array
    {
        $mapped = [];
        foreach ($foods as $food) {
            $mapped[$food['food_id']] = ['quantity' => $food['quantity'] ?? 1];
        }

        return $mapped;
    }

    private function calculateFoodsTotal(array $foods): float
    {
        if (empty($foods)) {
            return 0;
        }

        $ids = array_column($foods, 'food_id');
        $qtyById = [];
        foreach ($foods as $food) {
            $qtyById[$food['food_id']] = $food['quantity'] ?? 1;
        }

        $total = 0;
        foreach (Food::whereIn('id', $ids)->get(['id', 'price']) as $food) {
            $total += $food->price * ($qtyById[$food->id] ?? 1);
        }

        return (float) $total;
    }
}
