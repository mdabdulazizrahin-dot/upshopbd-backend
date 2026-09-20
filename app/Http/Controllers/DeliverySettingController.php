<?php

namespace App\Http\Controllers;

use App\Models\DeliverySetting;
use Illuminate\Http\Request;

class DeliverySettingController extends Controller
{
    public function index()
    {
        return response()->json(DeliverySetting::orderBy('id')->get());
    }

    public function update(Request $request, $id)
    {
        $setting = DeliverySetting::findOrFail($id);
        $setting->update($request->only(['delivery_type', 'charge', 'status']));
        return response()->json($setting);
    }

    public function store(Request $request)
    {
        $setting = DeliverySetting::create($request->only(['delivery_type', 'charge', 'status']));
        return response()->json($setting, 201);
    }
}
