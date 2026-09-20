<?php

namespace App\Http\Controllers;

use App\Models\CourierNote;
use Illuminate\Http\Request;

class CourierNoteController extends Controller
{
    public function index($orderId)
    {
        $notes = CourierNote::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($notes);
    }

    public function store(Request $request)
    {
        $note = CourierNote::create([
            'order_id' => $request->input('order_id'),
            'source' => $request->input('source', 'admin'),
            'message' => $request->input('message'),
            'created_by' => auth()->id() ?? null,
        ]);
        return response()->json($note, 201);
    }
}
