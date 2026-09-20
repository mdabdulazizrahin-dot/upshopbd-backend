<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staffUsers = User::whereIn('role', ['admin', 'moderator', 'editor', 'viewer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                // Ensure default permissions are included if custom permissions null
                $roleDefaults = [
                    'admin' => ['all'],
                    'moderator' => ['dashboard', 'orders', 'abandoned_checkouts', 'customers', 'courier', 'delivery', 'analytics'],
                    'editor' => ['dashboard', 'products', 'categories', 'banners', 'home_sections', 'pages'],
                    'viewer' => ['dashboard', 'products', 'categories', 'orders', 'analytics'],
                ];

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'permissions' => $user->permissions ?: ($roleDefaults[$user->role] ?? []),
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                ];
            });

        $summary = [
            'total_staff' => $staffUsers->count(),
            'admins_count' => $staffUsers->where('role', 'admin')->count(),
            'moderators_count' => $staffUsers->where('role', 'moderator')->count(),
            'editors_count' => $staffUsers->where('role', 'editor')->count(),
            'viewers_count' => $staffUsers->where('role', 'viewer')->count(),
        ];

        return response()->json([
            'data' => $staffUsers,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'শুধুমাত্র অ্যাডমিন স্টাফ যোগ করতে পারেন।'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,moderator,editor,viewer',
            'permissions' => 'nullable|array',
        ], [
            'email.unique' => 'এই ইমেইলটি ইতিমধ্যে ব্যবহৃত হয়েছে।',
            'password.min' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।',
        ]);

        $user = User::create([
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone' => $request->phone ? preg_replace('/[^0-9+]/', '', $request->phone) : null,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'permissions' => $request->permissions ?? null,
        ]);

        return response()->json([
            'message' => 'নতুন স্টাফ সদস্য সফলভাবে তৈরি করা হয়েছে।',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'permissions' => $user->permissions,
            ],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'শুধুমাত্র অ্যাডমিন স্টাফ তথ্য পরিবর্তন করতে পারেন।'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,moderator,editor,viewer',
            'permissions' => 'nullable|array',
        ]);

        // Prevent demoting the last remaining admin
        if ($user->role === 'admin' && $request->role !== 'admin') {
            $otherAdminsCount = User::where('role', 'admin')->where('id', '!=', $user->id)->count();
            if ($otherAdminsCount === 0) {
                return response()->json(['message' => 'ওয়েবসাইটে অন্তত একজন অ্যাডমিন থাকতে হবে। আপনি শেষ অ্যাডমিনের রোল পরিবর্তন করতে পারবেন না।'], 422);
            }
        }

        $user->name = trim($request->name);
        $user->email = strtolower(trim($request->email));
        $user->phone = $request->phone ? preg_replace('/[^0-9+]/', '', $request->phone) : null;
        $user->role = $request->role;
        $user->permissions = $request->permissions;

        if (!empty($request->password)) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'স্টাফ তথ্য সফলভাবে আপডেট করা হয়েছে।',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'permissions' => $user->permissions,
            ],
        ]);
    }

    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'শুধুমাত্র অ্যাডমিন স্টাফ অ্যাকাউন্ট মুছে ফেলতে পারেন।'], 403);
        }

        if (auth()->id() == $id) {
            return response()->json(['message' => 'আপনি নিজের অ্যাকাউন্ট ডিলিট করতে পারবেন না।'], 422);
        }

        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            $otherAdminsCount = User::where('role', 'admin')->where('id', '!=', $user->id)->count();
            if ($otherAdminsCount === 0) {
                return response()->json(['message' => 'ওয়েবসাইটে অন্তত একজন অ্যাডমিন থাকতে হবে। আপনি শেষ অ্যাডমিন অ্যাকাউন্ট ডিলিট করতে পারবেন না।'], 422);
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'স্টাফ সদস্য সফলভাবে মুছে ফেলা হয়েছে।',
        ]);
    }
}
