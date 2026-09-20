<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string)$request->query('search', ''));
        $typeFilter = $request->query('type', 'all'); // 'all', 'registered', 'guest'

        // 1. Registered Customers
        $registeredQuery = User::where('role', '!=', 'admin');

        if (!empty($search)) {
            $registeredQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $registeredUsers = $registeredQuery->with(['orders' => function ($q) {
            $q->select('id', 'user_id', 'total_amount', 'order_status', 'created_at')
              ->orderBy('created_at', 'desc');
        }])->get()->map(function ($user) {
            $orders = $user->orders;
            $ordersCount = $orders->count();
            $totalSpent = $orders->sum('total_amount');
            $latestOrder = $orders->first();

            // Clean fake placeholder email
            $displayEmail = $user->email;
            if (str_ends_with($displayEmail, '@phone.upshopbd.com')) {
                $displayEmail = null;
            }

            return [
                'id' => 'user_' . $user->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $displayEmail,
                'phone' => $user->phone,
                'type' => 'registered',
                'orders_count' => $ordersCount,
                'total_spent' => (float) $totalSpent,
                'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                'last_order_at' => $latestOrder ? $latestOrder->created_at->toISOString() : null,
                'last_order_status' => $latestOrder ? $latestOrder->order_status : null,
            ];
        });

        // 2. Guest Customers (from orders where user_id is null)
        $guestOrdersQuery = Order::whereNull('user_id')
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '');

        if (!empty($search)) {
            $guestOrdersQuery->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $guestOrders = $guestOrdersQuery->orderBy('created_at', 'desc')->get();

        // Group guest orders by customer_phone
        $guestCustomers = $guestOrders->groupBy('customer_phone')->map(function ($orders, $phone) {
            $latestOrder = $orders->first();
            $firstOrder = $orders->last();

            return [
                'id' => 'guest_' . md5($phone),
                'user_id' => null,
                'name' => $latestOrder->customer_name ?: 'Guest Customer',
                'email' => $latestOrder->customer_email ?: null,
                'phone' => $phone,
                'type' => 'guest',
                'orders_count' => $orders->count(),
                'total_spent' => (float) $orders->sum('total_amount'),
                'created_at' => $firstOrder->created_at ? $firstOrder->created_at->toISOString() : null,
                'last_order_at' => $latestOrder->created_at ? $latestOrder->created_at->toISOString() : null,
                'last_order_status' => $latestOrder->order_status,
            ];
        })->values();

        // Summary Statistics (calculated across full data)
        $totalRegistered = User::where('role', '!=', 'admin')->count();
        $totalGuests = Order::whereNull('user_id')
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->distinct('customer_phone')
            ->count('customer_phone');

        $totalStoreOrders = Order::count();
        $totalStoreRevenue = (float) Order::sum('total_amount');

        // Filter and Combine based on tab
        $combined = collect();
        if ($typeFilter === 'all' || $typeFilter === 'registered') {
            $combined = $combined->concat($registeredUsers);
        }
        if ($typeFilter === 'all' || $typeFilter === 'guest') {
            $combined = $combined->concat($guestCustomers);
        }

        // Sort by last order or created_at desc
        $combined = $combined->sortByDesc(function ($item) {
            return $item['last_order_at'] ?: $item['created_at'];
        })->values();

        return response()->json([
            'data' => $combined,
            'summary' => [
                'total_customers' => $totalRegistered + $totalGuests,
                'registered_count' => $totalRegistered,
                'guest_count' => $totalGuests,
                'total_orders' => $totalStoreOrders,
                'total_revenue' => $totalStoreRevenue,
            ],
        ]);
    }

    public function orders(Request $request)
    {
        $userId = $request->query('user_id');
        $phone = $request->query('phone');

        if (!$userId && !$phone) {
            return response()->json(['error' => 'user_id or phone parameter is required'], 400);
        }

        $query = Order::with(['items'])->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('customer_phone', $phone);
        }

        $orders = $query->get();

        return response()->json([
            'orders' => $orders,
            'total_count' => $orders->count(),
            'total_spent' => (float) $orders->sum('total_amount'),
        ]);
    }
}
