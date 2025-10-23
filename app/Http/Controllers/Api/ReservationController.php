<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    // Применяем middleware auth:sanctum
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Получить список бронирований текущего пользователя
    public function index(Request $request)
    {
        $reservations = $request->user()->reservations()->with('table.restaurant')->get();
        return response()->json($reservations);
    }

    // Создать новую бронь
    public function store(Request $request)
    {
        $data = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'date_time' => 'required|date',
        ]);

        // ❌ Нельзя бронировать в прошлом
        if (now()->greaterThan($data['date_time'])) {
            return response()->json([
                'message' => 'Нельзя бронировать время в прошлом.'
            ], 422);
        }

        // ❌ Проверяем, не занят ли стол
        $isTaken = \App\Models\Reservation::where('table_id', $data['table_id'])
            ->where('date_time', $data['date_time'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($isTaken) {
            return response()->json([
                'message' => 'Этот стол уже забронирован на это время.'
            ], 409);
        }

        // ✅ Создаём бронь
        $reservation = \App\Models\Reservation::create([
            'user_id' => $request->user()->id,
            'table_id' => $data['table_id'],
            'date_time' => $data['date_time'],
            'status' => 'pending',
        ]);

        return response()->json($reservation, 201);
    }

    protected $fillable = ['user_id', 'table_id', 'date_time', 'status'];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    // Опционально: показать одну бронь
    public function show($id, Request $request)
    {
        $reservation = $request->user()->reservations()->with('table.restaurant')->findOrFail($id);
        return response()->json($reservation);
    }

    // Опционально: отмена брони
    public function destroy($id, Request $request)
    {
        $reservation = $request->user()->reservations()->findOrFail($id);
        $reservation->delete();
        return response()->json(['message' => 'Reservation cancelled']);
    }
}
