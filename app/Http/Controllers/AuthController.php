<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);

        $identifier = trim((string)$request->input('email_or_phone', $request->input('email', $request->input('phone'))));
        if (empty($identifier)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['অনুগ্রহ করে আপনার ইমেইল অথবা ফোন নম্বর দিন।'],
            ]);
        }

        $email = null;
        $phone = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower($identifier);
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email_or_phone' => ['এই ইমেইলটি ইতিমধ্যে নিবন্ধিত রয়েছে। অন্য ইমেইল ব্যবহার করুন বা লগইন করুন।'],
                ]);
            }
        } else {
            $phone = preg_replace('/[^0-9+]/', '', $identifier);
            if (empty($phone) || strlen($phone) < 10) {
                throw ValidationException::withMessages([
                    'email_or_phone' => ['সঠিক ইমেইল অথবা ফোন নম্বর দিন (কমপক্ষে ১১ ডিজিট)।'],
                ]);
            }
            if (User::where('phone', $phone)->exists()) {
                throw ValidationException::withMessages([
                    'email_or_phone' => ['এই ফোন নম্বরটি ইতিমধ্যে নিবন্ধিত রয়েছে। লগইন করুন।'],
                ]);
            }
            $email = "{$phone}@phone.upshopbd.com";
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->permissions ?? [],
                'phone' => $user->phone ?? null,
                'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $identifier = trim((string)$request->input('email_or_phone', $request->input('email')));
        if (empty($identifier)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['অনুগ্রহ করে আপনার ইমেইল অথবা ফোন নম্বর দিন।'],
            ]);
        }

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('email', "{$identifier}@phone.upshopbd.com")
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['ইমেইল/ফোন নম্বর অথবা পাসওয়ার্ড ভুল হয়েছে।'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->permissions ?? [],
                'phone' => $user->phone ?? null,
                'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->permissions ?? [],
            'phone' => $user->phone ?? null,
            'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $user->update($request->only(['name', 'phone']));
        return response()->json($user);
    }

    public function forgotPassword(Request $request)
    {
        $identifier = trim((string)$request->input('email_or_phone', $request->input('identifier')));
        if (empty($identifier)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['অনুগ্রহ করে আপনার নিবন্ধিত ইমেইল অথবা ফোন নম্বর দিন।'],
            ]);
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $identifier);

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('email', "{$identifier}@phone.upshopbd.com")
            ->when(!empty($cleanPhone), function ($q) use ($cleanPhone) {
                return $q->orWhere('phone', $cleanPhone);
            })
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['এই ইমেইল বা ফোন নম্বরে কোনো অ্যাকাউন্ট পাওয়া যায়নি। অনুগ্রহ করে সঠিক তথ্য দিন।'],
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string) mt_rand(100000, 999999);

        // Cache for 15 minutes
        $cacheKey = "pwd_reset_{$user->id}";
        Cache::put($cacheKey, $otp, now()->addMinutes(15));
        Log::info("Password reset OTP for user {$user->id} ({$user->name}): {$otp}");

        // Mask phone/email for response
        $contactDisplay = $user->phone ?: $user->email;
        if ($user->phone && strlen($user->phone) >= 7) {
            $contactDisplay = substr($user->phone, 0, 3) . '****' . substr($user->phone, -4);
        }

        return response()->json([
            'message' => "আপনার {$contactDisplay} এ একটি ৬ ডিজিটের ওটিপি ভেরিফিকেশন কোড পাঠানো হয়েছে।",
            'user_id' => $user->id,
            'contact' => $contactDisplay,
            'otp' => $otp, // Available for local/demo testing
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'otp' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'পাসওয়ার্ড নিশ্চিতকরণ মিলছে না।',
            'password.min' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।',
        ]);

        $user = User::findOrFail($request->user_id);
        $cacheKey = "pwd_reset_{$user->id}";
        $cachedOtp = Cache::get($cacheKey);

        if (!$cachedOtp || $cachedOtp !== trim($request->otp)) {
            throw ValidationException::withMessages([
                'otp' => ['প্রদত্ত ওটিপি কোডটি সঠিক নয় অথবা মেয়াদ্ উত্তীর্ণ হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে। এখন নতুন পাসওয়ার্ড দিয়ে লগইন করুন।',
            'success' => true,
        ]);
    }
}
