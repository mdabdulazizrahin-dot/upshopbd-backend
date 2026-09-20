<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $items = DB::table('wishlist')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($items->isEmpty()) {
            return response()->json([]);
        }

        $productIds = $items->pluck('product_id')->unique()->filter()->values();
        $products = Product::with(['images', 'category', 'variations'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $result = $items->map(function ($item) use ($products) {
            $product = $products->get($item->product_id);
            if (!$product) {
                return null;
            }

            $productArray = $product->toArray();
            $productArray['product_images'] = $product->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'image_url' => $img->image_url,
                    'is_main' => (bool) $img->is_main,
                ];
            });

            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'product_id' => $item->product_id,
                'created_at' => $item->created_at,
                'product' => $productArray,
                'products' => $productArray,
            ];
        })->filter()->values();

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $exists = DB::table('wishlist')
            ->where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already in wishlist'], 409);
        }

        DB::table('wishlist')->insert([
            'user_id' => auth()->id(),
            'product_id' => $request->product_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Added to wishlist'], 201);
    }

    public function destroy($id)
    {
        DB::table('wishlist')
            ->where('user_id', auth()->id())
            ->where(function ($q) use ($id) {
                $q->where('product_id', $id)
                  ->orWhere('id', $id);
            })
            ->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }
}