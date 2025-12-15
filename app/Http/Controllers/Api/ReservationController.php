<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            $query = Reservation::with(['user', 'table']);

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
            ]);

            $dateTime = $this->normalizeDateTime($validated['date_time']);

            $existingReservation = Reservation::where('table_id', $validated['table_id'])
                ->whereBetween('date_time', [
                    Carbon::parse($dateTime)->subHours(2),
                    Carbon::parse($dateTime)->addHours(2),
                ])
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingReservation) {
                return response()->json([
                    'message' => 'Стол уже забронирован на это время или соседнее время',
                ], 409);
            }

            $user = Auth::user();

            $reservation = Reservation::create([
                'user_id' => $user?->id,
                'table_id' => $validated['table_id'],
                'date_time' => $dateTime,
                'status' => 'pending',
            ]);

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
            $reservation = Reservation::with(['user', 'table'])->findOrFail($id);

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
            ]);

            $reservation->update($validated);

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
                'reservation_id' => 'nullable|exists:reservations,id',
            ]);

            $dateTime = $this->normalizeDateTime($validated['date_time']);

            $query = Reservation::where('table_id', $validated['table_id'])
                ->whereBetween('date_time', [
                    Carbon::parse($dateTime)->subHours(2),
                    Carbon::parse($dateTime)->addHours(2),
                ])
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
}
