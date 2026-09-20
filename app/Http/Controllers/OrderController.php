<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\BlockedEntity;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['items'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $orders]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
            'delivery_address' => 'required|string',
        ]);

        $clientIp = $request->ip() ?: '127.0.0.1';
        $phone = preg_replace('/[^0-9+]/', '', (string)$request->customer_phone);

        // 1. Blacklist Check: Blocked Phone or IP
        $isBlocked = BlockedEntity::where(function ($q) use ($clientIp, $phone) {
            $q->where('type', 'ip')->where('value', $clientIp)
              ->orWhere(function ($q2) use ($phone) {
                  $q2->where('type', 'phone')->where('value', $phone);
              });
        })->first();

        if ($isBlocked) {
            return response()->json([
                'message' => 'অস্বাভাবিক বা সন্দেহজনক কার্যকলাপের কারণে এই নম্বর বা ডিভাইস থেকে অর্ডার নেওয়া স্থগিত রয়েছে। সাহায্যের জন্য আমাদের কাস্টমার সাপোর্টে যোগাযোগ করুন।',
                'blocked' => true,
            ], 403);
        }

        // 2. Honeypot Check: if bot_trap_field or hp_field is filled, it is a bot
        $trapField = $request->input('bot_trap_field', $request->input('hp_field'));
        if (!empty($trapField)) {
            // Auto-block the bot IP
            BlockedEntity::firstOrCreate(
                ['type' => 'ip', 'value' => $clientIp],
                ['reason' => 'Automated Bot Detected (Honeypot Trap Triggered)']
            );
            return response()->json([
                'message' => 'বট বা রোবোটিক কার্যকলাপ শনাক্ত করা হয়েছে। আপনার অর্ডারটি গ্রহণ করা হয়নি।',
                'blocked' => true,
            ], 403);
        }

        // 3. Velocity / Rapid Order Check
        $recentOrdersCount = Order::where(function ($q) use ($clientIp, $phone) {
            $q->where('ip_address', $clientIp)
              ->orWhere('customer_phone', $phone);
        })->where('created_at', '>=', now()->subMinutes(3))->count();

        $isSuspicious = false;
        $suspiciousReason = null;

        if ($recentOrdersCount >= 3) {
            $isSuspicious = true;
            $suspiciousReason = "অতিরিক্ত ঘন ঘন অর্ডার (গত ৩ মিনিটে {$recentOrdersCount}টি অর্ডার)";
        }

        // 4. Suspicious Pattern Check (e.g. 01700000000 repeating digits)
        if (preg_match('/^01[3-9](\d)\1{7}$/', $phone)) {
            $isSuspicious = true;
            $suspiciousReason = 'অস্বাভাবিক ফোন নম্বর প্যাটার্ন (একই ডিজিট বারবার)';
        }

        $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $request->user_id ?? null,
            'ip_address' => $clientIp,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $request->customer_email ?? null,
            'delivery_address' => $request->delivery_address,
            'district' => $request->district ?? null,
            'upazila' => $request->upazila ?? null,
            'order_note' => $request->order_note ?? null,
            'delivery_type' => $request->delivery_type ?? 'outside_dhaka',
            'subtotal' => $request->subtotal ?? 0,
            'delivery_charge' => $request->delivery_charge ?? 0,
            'total_amount' => $request->total_amount ?? 0,
            'payment_method' => $request->payment_method ?? 'cod',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $suspiciousReason,
        ]);

        if ($request->items) {
            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'] ?? '',
                    'variation_id' => $item['variation_id'] ?? null,
                    'variation_attributes' => $item['variation_attributes'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'] ?? $item['price'] ?? 0,
                    'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0,
                    'total_price' => $item['total_price'] ?? (($item['unit_price'] ?? $item['price'] ?? 0) * $item['quantity']),
                ]);
            }
        }

        return response()->json(['order' => $order->load('items')], 201);
    }

    public function show($id)
    {
        $order = Order::with(['items'])->findOrFail($id);
        return response()->json(['order' => $order]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $data = $request->all();

        // Auto payment status
        if (isset($data['order_status'])) {
            if ($data['order_status'] === 'delivered') $data['payment_status'] = 'paid';
            if ($data['order_status'] === 'cancelled') $data['payment_status'] = 'cancelled';
        }

        $order->update($data);
        return response()->json(['data' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->items()->delete();
        $order->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No order IDs provided'], 400);
        }

        $orders = Order::whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $order->items()->delete();
            $order->delete();
        }

        return response()->json([
            'message' => count($orders) . ' orders deleted successfully',
            'count' => count($orders),
        ]);
    }

    public function updateItem(Request $request, $id)
    {
        $item = OrderItem::findOrFail($id);
        $data = $request->only(['quantity', 'unit_price', 'total_price', 'price']);
        if (isset($data['unit_price'])) {
            $data['price'] = $data['unit_price'];
        }
        $item->update($data);

        $order = $item->order;
        if ($order) {
            $subtotal = $order->items()->sum('total_price');
            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + ($order->delivery_charge ?? 0),
            ]);
        }

        return response()->json(['data' => $item]);
    }

    public function deleteItem($id)
    {
        $item = OrderItem::findOrFail($id);
        $order = $item->order;
        $item->delete();

        if ($order) {
            $subtotal = $order->items()->sum('total_price');
            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + ($order->delivery_charge ?? 0),
            ]);
        }

        return response()->json(['message' => 'Item deleted']);
    }

    public function userOrders(Request $request)
    {
        $orders = Order::with(['items'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $orders]);
    }

    public function userOrderShow($id)
    {
        $order = Order::with(['items'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        return response()->json(['data' => $order]);
    }

    public function cancel($id)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);
        $order->update(['order_status' => 'cancelled', 'payment_status' => 'cancelled']);
        return response()->json(['data' => $order]);
    }

    public function trackByPhone(Request $request)
    {
        $phone = $request->query('phone');
        if (!$phone) {
            return response()->json(['error' => 'Phone number required'], 400);
        }

        $orders = Order::with(['items'])
            ->where('customer_phone', $phone)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json(['orders' => $orders]);
    }

    public function blockedEntities(Request $request)
    {
        $entities = BlockedEntity::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $entities]);
    }

    public function blockEntity(Request $request)
    {
        $request->validate([
            'type' => 'required|in:phone,ip',
            'value' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $val = trim($request->value);
        if ($request->type === 'phone') {
            $val = preg_replace('/[^0-9+]/', '', $val);
        }

        $entity = BlockedEntity::firstOrCreate(
            ['type' => $request->type, 'value' => $val],
            ['reason' => $request->reason ?? 'Blocked by Admin']
        );

        return response()->json([
            'message' => ($request->type === 'phone' ? 'কাস্টমার ফোন নম্বর' : 'IP অ্যাড্রেস') . ' সফলভাবে ব্লক করা হয়েছে।',
            'data' => $entity,
        ]);
    }

    public function unblockEntity($id)
    {
        $entity = BlockedEntity::findOrFail($id);
        $entity->delete();

        return response()->json([
            'message' => 'সফলভাবে আনব্লক করা হয়েছে।',
        ]);
    }
}