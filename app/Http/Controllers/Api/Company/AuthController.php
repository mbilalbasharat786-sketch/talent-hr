<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendVerificationCode(User $user, string $code, string $purpose = 'email verification'): void
    {
        $subject = $purpose === 'two-factor authentication'
            ? 'TalentHR two-factor login code'
            : 'TalentHR company email verification code';

        Mail::raw(
            "Your TalentHR {$purpose} code is: {$code}\n\nThis 6-digit code will expire soon. If you did not request this code, please ignore this email.",
            fn ($message) => $message->to($user->email)->subject($subject)
        );
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:companies,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $verificationCode = $this->generateCode();

        $data = DB::transaction(function () use ($request, $verificationCode) {
            $company = Company::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => 'pending',
                'trust_level' => 'basic',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'company',
                'company_id' => $company->id,
                'status' => 'active',
                'email_verification_code' => $verificationCode,
                'email_verification_expires_at' => now()->addMinutes(15),
            ]);

            return [
                'company' => $company,
                'user' => $user,
            ];
        });

        $this->sendVerificationCode($data['user'], $verificationCode);

        ActivityLogger::log(
            'register',
            'company_auth',
            "Company {$data['company']->name} registered.",
            $request,
            $data['user']->id
        );

        return response()->json([
            'message' => 'Company registered successfully. Please verify your email before login.',
            'email_verification_required' => true,
            'user' => $data['user'],
            'company' => $data['company'],
            'verification_code' => app()->environment('local') ? $verificationCode : null,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('company')
            ->where('email', $request->email)
            ->where('role', 'company')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid company owner credentials.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Company owner account is inactive.',
            ], 403);
        }

        if (! $user->email_verified_at) {
            $verificationCode = $this->generateCode();

            $user->update([
                'email_verification_code' => $verificationCode,
                'email_verification_expires_at' => now()->addMinutes(15),
            ]);

            $this->sendVerificationCode($user, $verificationCode);

            return response()->json([
                'message' => 'Email verification is required before login.',
                'email_verification_required' => true,
                'verification_code' => app()->environment('local') ? $verificationCode : null,
            ], 403);
        }

        if ($user->two_factor_enabled) {
            $twoFactorCode = $this->generateCode();

            $user->update([
                'two_factor_code' => $twoFactorCode,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            $this->sendVerificationCode($user, $twoFactorCode, 'two-factor authentication');

            ActivityLogger::log(
                'two_factor_challenge',
                'company_auth',
                'Company owner two-factor verification code generated.',
                $request,
                $user->id
            );

            return response()->json([
                'message' => 'Two-factor verification code sent.',
                'requires_two_factor' => true,
                'email' => $user->email,
                'two_factor_code' => app()->environment('local') ? $twoFactorCode : null,
            ]);
        }

        $token = $user->createToken('company-owner-token')->plainTextToken;

        ActivityLogger::log(
            'login',
            'company_auth',
            'Company owner logged in.',
            $request,
            $user->id
        );

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'company')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Company owner account not found.',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        if (
            $user->email_verification_code !== $request->code ||
            ! $user->email_verification_expires_at ||
            now()->greaterThan($user->email_verification_expires_at)
        ) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ]);

        ActivityLogger::log(
            'verify_email',
            'company_auth',
            'Company owner email verified.',
            $request,
            $user->id
        );

        Notification::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'type' => 'system_alert',
            'title' => 'Email verified',
            'message' => 'Your company owner email was verified successfully.',
        ]);

        return response()->json([
            'message' => 'Email verified successfully. You can now login.',
        ]);
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'company')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Company owner account not found.',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $verificationCode = $this->generateCode();

        $user->update([
            'email_verification_code' => $verificationCode,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendVerificationCode($user, $verificationCode);

        ActivityLogger::log(
            'resend_verification_code',
            'company_auth',
            'Company owner verification code resent.',
            $request,
            $user->id
        );

        return response()->json([
            'message' => 'Verification code resent successfully.',
            'verification_code' => app()->environment('local') ? $verificationCode : null,
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::with('company')
            ->where('email', $request->email)
            ->where('role', 'company')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Company owner account not found.',
            ], 404);
        }

        if (! $user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled for this account.',
            ], 422);
        }

        if (
            $user->two_factor_code !== $request->code ||
            ! $user->two_factor_expires_at ||
            now()->greaterThan($user->two_factor_expires_at)
        ) {
            return response()->json([
                'message' => 'Invalid or expired two-factor code.',
            ], 422);
        }

        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        $token = $user->createToken('company-owner-token')->plainTextToken;

        ActivityLogger::log(
            'verify_2fa',
            'company_auth',
            'Company owner completed two-factor verification.',
            $request,
            $user->id
        );

        return response()->json([
            'message' => 'Two-factor verification successful.',
            'token' => $token,
            'role' => $user->role,
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('company'),
        ]);
    }

    public function logout(Request $request)
    {
        ActivityLogger::log(
            'logout',
            'company_auth',
            'Company owner logged out.',
            $request
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
