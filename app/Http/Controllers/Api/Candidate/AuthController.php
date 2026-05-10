<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

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

        // --- Naya Verification Check ---
        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Your email is not verified. Please verify your email to login.',
                'email' => $user->email,
                'needs_verification' => true
            ], 403);
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
            $otp = rand(100000, 999999); // 6 random code generate kiya

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
                'email_verification_code' => $otp, // User model ke column mein save kiya
                'email_verification_expires_at' => Carbon::now()->addMinutes(15),
                'email_verified_at' => null, // Initial registration par null rakha
            ]);

            // Email send karein
            Mail::raw("Your TalentHR verification code is: $otp", function($message) use ($user) {
                $message->to($user->email)->subject('Verify Your Candidate Account');
            });

            // Database notification create karein
            Notification::create([
                'user_id' => $user->id,
                'type' => 'registration',
                'title' => 'Verify Your Email',
                'message' => "A verification code $otp has been sent to your email.",
            ]);

            ActivityLogger::log(
                'register',
                'candidate_auth',
                'New candidate registered, awaiting verification.',
                $request,
                $user->id
            );

            return response()->json([
                'message' => 'OTP sent to your email. Please verify to complete registration.',
                'email' => $user->email,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // --- Naya Verification Function ---
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        $user = User::where('email', $request->email)
            ->where('email_verification_code', $request->otp)
            ->where('email_verification_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        return response()->json([
            'message' => 'Email verified successfully! You can now login.'
        ]);
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
