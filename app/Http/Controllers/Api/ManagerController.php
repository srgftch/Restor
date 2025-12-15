<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }


    // GET /api/manager/users - список пользователей
    public function getUsers(Request $request)
    {
        $users = User::users()
            ->withCount(['reservations', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    // POST /api/manager/users/{id}/block - блокировка пользователя
    public function blockUser($id, Request $request)
    {
        $user = User::users()->findOrFail($id);
        $user->update(['is_blocked' => true]);

        return response()->json([
            'message' => 'User blocked successfully',
            'user' => $user
        ]);
    }

    // POST /api/manager/users/{id}/unblock - разблокировка пользователя
    public function unblockUser($id, Request $request)
    {
        $user = User::users()->findOrFail($id);
        $user->update(['is_blocked' => false]);

        return response()->json([
            'message' => 'User unblocked successfully',
            'user' => $user
        ]);
    }
}
