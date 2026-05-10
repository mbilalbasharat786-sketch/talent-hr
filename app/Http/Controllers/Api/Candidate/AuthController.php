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
    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendVerificationCode(User $user, string $code): void
    {
        Mail::raw(
            "Your TalentHR candidate email verification code is: {$code}\n\nThis 6-digit code will expire in 15 minutes. If you did not request this code, please ignore this email.",
            fn ($message) => $message->to($user->email)->subject('TalentHR candidate email verification code')
        );
    }

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

        if (!$user->email_verified_at) {
            $verificationCode = $this->generateCode();

            $user->update([
                'email_verification_code' => $verificationCode,
                'email_verification_expires_at' => now()->addMinutes(15),
            ]);

            $this->sendVerificationCode($user, $verificationCode);

            return response()->json([
                'message' => 'Email verification is required before login. A new code was sent to your email.',
                'email' => $user->email,
                'email_verification_required' => true,
                'verification_code' => app()->environment('local') ? $verificationCode : null,
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
            'skills' => ['nullable'],
            'education' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'portfolio_links' => ['nullable', 'array'],
            'portfolio_links.*.title' => ['required_with:portfolio_links', 'string', 'max:255'],
            'portfolio_links.*.url' => ['required_with:portfolio_links', 'url'],
        ]);

        try {
            $otp = $this->generateCode();
            $skills = is_array($request->skills)
                ? $request->skills
                : array_values(array_filter(array_map('trim', explode(',', (string) $request->skills))));

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'candidate',
                'status' => 'active',
                'phone' => $request->phone,
                'skills' => $skills,
                'education' => $request->education,
                'experience' => $request->experience,
                'email_verification_code' => $otp,
                'email_verification_expires_at' => Carbon::now()->addMinutes(15),
                'email_verified_at' => null,
            ]);

            $this->sendVerificationCode($user, $otp);

            // Database notification create karein
            Notification::create([
                'user_id' => $user->id,
                'type' => 'system_alert',
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
                'email_verification_required' => true,
                'verification_code' => app()->environment('local') ? $otp : null,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'candidate')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Candidate account not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        if (
            $user->email_verification_code !== $request->code ||
            !$user->email_verification_expires_at ||
            now()->greaterThan($user->email_verification_expires_at)
        ) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null
        ]);

        ActivityLogger::log(
            'verify_email',
            'candidate_auth',
            'Candidate email verified.',
            $request,
            $user->id
        );

        Notification::create([
            'user_id' => $user->id,
            'type' => 'system_alert',
            'title' => 'Email verified',
            'message' => 'Your candidate email was verified successfully.',
        ]);

        return response()->json(['message' => 'Email verified successfully. You can now login.']);
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'candidate')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Candidate account not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        $verificationCode = $this->generateCode();

        $user->update([
            'email_verification_code' => $verificationCode,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendVerificationCode($user, $verificationCode);

        ActivityLogger::log(
            'resend_verification_code',
            'candidate_auth',
            'Candidate verification code resent.',
            $request,
            $user->id
        );

        return response()->json([
            'message' => 'Verification code resent successfully.',
            'verification_code' => app()->environment('local') ? $verificationCode : null,
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
