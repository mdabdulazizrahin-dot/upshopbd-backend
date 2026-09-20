<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCheckout;
use Illuminate\Http\Request;

class AbandonedCheckoutController extends Controller
{
    // Admin: সব abandoned checkout দেখা
    public function index()
    {
        $checkouts = AbandonedCheckout::orderBy('last_activity', 'desc')->get();
        return response()->json($checkouts);
    }

    // Checkout page থেকে auto-save
    public function save(Request $request)
    {
        $sessionId = $request->input('session_id') ?? session()->getId();

        $checkout = AbandonedCheckout::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'customer_name' => $request->input('customer_name'),
                'customer_phone' => $request->input('customer_phone'),
                'customer_email' => $request->input('customer_email'),
                'district' => $request->input('district'),
                'upazila' => $request->input('upazila'),
                'delivery_address' => $request->input('delivery_address'),
                'subtotal' => $request->input('subtotal', 0),
                'delivery_charge' => $request->input('delivery_charge', 0),
                'total_amount' => $request->input('total_amount', 0),
                'items' => $request->input('items', []),
                'last_activity' => now(),
            ]
        );

        return response()->json($checkout);
    }

    // Order confirm হলে abandoned checkout delete করা
    public function delete(Request $request)
    {
        $sessionId = $request->input('session_id');
        if ($sessionId) {
            AbandonedCheckout::where('session_id', $sessionId)->delete();
        }
        return response()->json(['message' => 'deleted']);
    }

    // Admin: একটা delete করা
    public function destroy($id)
    {
        AbandonedCheckout::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }
}
