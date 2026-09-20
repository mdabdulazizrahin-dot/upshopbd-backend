<?php

namespace App\Http\Controllers;

use App\Models\CourierSetting;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CourierSettingController extends Controller
{
    public function index()
    {
        return response()->json(CourierSetting::all());
    }

    public function update(Request $request, $id)
    {
        $courier = CourierSetting::findOrFail($id);
        $courier->update($request->only([
            'api_key', 'api_secret', 'access_token',
            'is_enabled', 'default_weight',
        ]));
        return response()->json($courier);
    }

    public function setDefault($id)
    {
        CourierSetting::where('is_default', true)->update(['is_default' => false]);
        $courier = CourierSetting::findOrFail($id);
        $courier->update(['is_default' => true]);
        return response()->json($courier);
    }

    public function book(Request $request)
    {
        $orderId = $request->input('order_id');
        $order = Order::with('items')->findOrFail($orderId);

        // Default courier খুঁজে নাও
        $courier = CourierSetting::where('is_default', true)
            ->where('is_enabled', true)
            ->first();

        if (!$courier) {
            $courier = CourierSetting::where('is_enabled', true)->first();
        }

        if (!$courier) {
            return response()->json(['error' => 'কোনো courier enabled নেই। Courier Settings এ API key দিয়ে enable করুন।'], 422);
        }

        try {
            if ($courier->courier_name === 'steadfast') {
                return $this->bookSteadfast($order, $courier);
            } elseif ($courier->courier_name === 'pathao') {
                return $this->bookPathao($order, $courier);
            } elseif ($courier->courier_name === 'redx') {
                return $this->bookRedx($order, $courier);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['error' => 'Courier not supported'], 422);
    }

    private function bookSteadfast($order, $courier)
    {
        if (!$courier->api_key || !$courier->api_secret) {
            return response()->json(['error' => 'Steadfast API Key এবং Secret Key দিন।'], 422);
        }

        $response = Http::withHeaders([
            'Api-Key' => $courier->api_key,
            'Secret-Key' => $courier->api_secret,
            'Content-Type' => 'application/json',
        ])->post('https://portal.steadfast.com.bd/api/v1/create_order', [
            'invoice' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => $order->delivery_address . ', ' . $order->upazila . ', ' . $order->district,
            'cod_amount' => $order->total_amount,
            'note' => $order->order_note ?? '',
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['consignment']['tracking_code'])) {
            $order->update([
                'courier_name' => 'Steadfast',
                'tracking_number' => $data['consignment']['tracking_code'],
                'courier_tracking_link' => 'https://steadfast.com.bd/t/' . $data['consignment']['tracking_code'],
                'order_status' => 'processing',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Steadfast এ booking সফল!',
                'tracking_number' => $data['consignment']['tracking_code'],
                'order' => $order,
            ]);
        }

        return response()->json(['error' => $data['message'] ?? 'Steadfast booking failed'], 422);
    }

    private function bookPathao($order, $courier)
    {
        if (!$courier->api_key || !$courier->access_token) {
            return response()->json(['error' => 'Pathao Client ID এবং Access Token দিন।'], 422);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $courier->access_token,
            'Content-Type' => 'application/json',
        ])->post('https://api-hermes.pathao.com/aladdin/api/v1/orders', [
            'store_id' => $courier->api_secret,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => $order->delivery_address,
            'recipient_city' => 1,
            'recipient_zone' => 1,
            'delivery_type' => 48,
            'item_type' => 2,
            'special_instruction' => $order->order_note ?? '',
            'item_quantity' => 1,
            'item_weight' => $courier->default_weight,
            'amount_to_collect' => $order->total_amount,
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['data']['consignment_id'])) {
            $order->update([
                'courier_name' => 'Pathao',
                'tracking_number' => $data['data']['consignment_id'],
                'order_status' => 'processing',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Pathao তে booking সফল!',
                'tracking_number' => $data['data']['consignment_id'],
                'order' => $order,
            ]);
        }

        return response()->json(['error' => $data['message'] ?? 'Pathao booking failed'], 422);
    }

    private function bookRedx($order, $courier)
    {
        if (!$courier->api_key) {
            return response()->json(['error' => 'RedX API Access Token দিন।'], 422);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $courier->api_key,
            'Content-Type' => 'application/json',
        ])->post('https://openapi.redx.com.bd/v1.0.0-beta/parcel', [
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_area' => $order->district . ', ' . $order->upazila,
            'delivery_area_id' => 1,
            'customer_address' => $order->delivery_address,
            'merchant_invoice_id' => $order->order_number,
            'cash_collection_amount' => $order->total_amount,
            'parcel_weight' => $courier->default_weight * 1000,
            'instruction' => $order->order_note ?? '',
            'value' => $order->total_amount,
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['tracking_id'])) {
            $order->update([
                'courier_name' => 'RedX',
                'tracking_number' => $data['tracking_id'],
                'courier_tracking_link' => 'https://redx.com.bd/track-parcel/?trackingId=' . $data['tracking_id'],
                'order_status' => 'processing',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'RedX এ booking সফল!',
                'tracking_number' => $data['tracking_id'],
                'order' => $order,
            ]);
        }

        return response()->json(['error' => $data['message'] ?? 'RedX booking failed'], 422);
    }
}
