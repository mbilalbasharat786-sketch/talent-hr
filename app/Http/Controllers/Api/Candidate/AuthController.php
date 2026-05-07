<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'candidate')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid candidate credentials.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Candidate account is inactive.',
            ], 403);
        }

        $token = $user->createToken('candidate-user-token')->plainTextToken;

        ActivityLogger::log(
            'login',
            'candidate_auth',
            'Candidate logged in.',
            $request,
            $user->id
        );

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => $user,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'skills' => ['required', 'string', 'min:3'],
            'education' => ['required', 'string', 'min:10'],
            'experience' => ['required', 'string', 'min:10'],
            'portfolio_links' => ['nullable', 'array'],
            'portfolio_links.*.title' => ['required_with:portfolio_links', 'string', 'max:255'],
            'portfolio_links.*.url' => ['required_with:portfolio_links', 'url'],
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'candidate',
                'status' => 'active',
                'phone' => $request->phone,
                'skills' => is_array($request->skills) ? $request->skills : explode(',', $request->skills),
                'education' => $request->education,
                'experience' => $request->experience,
                'email_verified_at' => now(),
            ]);

            ActivityLogger::log(
                'register',
                'candidate_auth',
                'New candidate registered.',
                $request,
                $user->id
            );

            return response()->json([
                'message' => 'Candidate registered successfully.',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->loadCount(['jobApplications', 'internships']),
        ]);
    }

    public function logout(Request $request)
    {
        ActivityLogger::log(
            'logout',
            'candidate_auth',
            'Candidate logged out.',
            $request
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
