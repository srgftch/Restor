<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // Применяем middleware auth:sanctum
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // === USER MANAGEMENT ===

    // GET /api/admin/users - список всех пользователей
    public function getUsers(Request $request)
    {
        $users = User::withCount(['reservations', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    // POST /api/admin/users/{id}/block - блокировка пользователя
    public function blockUser($id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Cannot block admin user'
            ], 422);
        }

        $user->update(['is_blocked' => true]);

        return response()->json([
            'message' => 'User blocked successfully',
            'user' => $user
        ]);
    }

    // POST /api/admin/users/{id}/unblock - разблокировка пользователя
    public function unblockUser($id, Request $request)
    {
        $user = User::findOrFail($id);
        $user->update(['is_blocked' => false]);

        return response()->json([
            'message' => 'User unblocked successfully',
            'user' => $user
        ]);
    }

    // DELETE /api/admin/users/{id} - удаление пользователя
    public function deleteUser($id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Cannot delete admin user'
            ], 422);
        }

        $user->reservations()->delete();
        $user->payments()->delete();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // === MANAGER MANAGEMENT ===

    // GET /api/admin/managers - список менеджеров
    public function getManagers(Request $request)
    {
        $managers = User::managers()
            ->withCount(['reservations', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($managers);
    }

    // POST /api/admin/managers - создание менеджера
    public function createManager(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $manager = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_MANAGER,
        ]);

        return response()->json($manager, 201);
    }

    // POST /api/admin/managers/{id}/block - блокировка менеджера
    public function blockManager($id, Request $request)
    {
        $manager = User::managers()->findOrFail($id);
        $manager->update(['is_blocked' => true]);

        return response()->json([
            'message' => 'Manager blocked successfully',
            'manager' => $manager
        ]);
    }

    // POST /api/admin/managers/{id}/unblock - разблокировка менеджера
    public function unblockManager($id, Request $request)
    {
        $manager = User::managers()->findOrFail($id);
        $manager->update(['is_blocked' => false]);

        return response()->json([
            'message' => 'Manager unblocked successfully',
            'manager' => $manager
        ]);
    }

    // DELETE /api/admin/managers/{id} - удаление менеджера
    public function deleteManager($id, Request $request)
    {
        $manager = User::managers()->findOrFail($id);

        $manager->reservations()->delete();
        $manager->payments()->delete();
        $manager->tokens()->delete();
        $manager->delete();

        return response()->json(['message' => 'Manager deleted successfully']);
    }
}
