<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'role' => ['nullable', Rule::in(['super_admin', 'company', 'hr', 'candidate'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with('company:id,name')
            ->select('id', 'name', 'email', 'role', 'status', 'company_id', 'phone', 'email_verified_at', 'created_at', 'updated_at')
            ->when($request->role, function ($query) use ($request) {
                $query->where('role', $request->role);
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function show(User $user)
    {
        $user->load('company:id,name,email,status,trust_level');

        return response()->json([
            'user' => $user,
        ]);
    }

    public function deactivate(Request $request, User $user)
    {
        if ($user->role === 'super_admin') {
            return response()->json([
                'message' => 'Super admin cannot be deactivated from user management.',
            ], 422);
        }

        $user->update([
            'status' => 'inactive',
        ]);

        $user->tokens()->delete();

        ActivityLogger::log(
            'deactivate',
            'user_management',
            "User {$user->name} ({$user->role}) deactivated.",
            $request
        );

        return response()->json([
            'message' => 'User deactivated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    public function activate(Request $request, User $user)
    {
        $user->update([
            'status' => 'active',
        ]);

        ActivityLogger::log(
            'activate',
            'user_management',
            "User {$user->name} ({$user->role}) activated.",
            $request
        );

        return response()->json([
            'message' => 'User activated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
