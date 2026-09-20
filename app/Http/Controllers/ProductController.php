<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images', 'variations']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->category) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }
        if ($request->limit) {
            $query->limit($request->limit);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function show($slug)
    {
        $query = Product::with(['category', 'images', 'variations']);
        if (is_numeric($slug)) {
            $product = $query->findOrFail($slug);
        } else {
            $product = $query->where('seo_slug', $slug)->firstOrFail();
        }
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'product_type' => $request->product_type ?? 'simple',
            'price' => $request->price ?? 0,
            'sale_price' => $request->sale_price,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'category_id' => $request->category_id,
            'status' => $request->status ?? 'active',
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_slug' => $request->seo_slug,
        ]);

        if ($request->images) {
            foreach ($request->images as $img) {
                $product->images()->create([
                    'image_url' => $img['image_url'],
                    'is_main' => $img['is_main'] ?? false,
                    'sort_order' => $img['sort_order'] ?? 0,
                ]);
            }
        }

        if ($request->variations) {
            foreach ($request->variations as $v) {
                $product->variations()->create([
                    'attributes' => $v['attributes'],
                    'price' => $v['price'],
                    'sale_price' => $v['sale_price'] ?? null,
                    'stock_quantity' => $v['stock_quantity'] ?? 0,
                    'image_url' => $v['image_url'] ?? null,
                ]);
            }
        }

        return response()->json($product->load(['category', 'images', 'variations']), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update([
            'name' => $request->name ?? $product->name,
            'description' => $request->description ?? $product->description,
            'product_type' => $request->product_type ?? $product->product_type,
            'price' => $request->price ?? $product->price,
            'sale_price' => $request->sale_price,
            'stock_quantity' => $request->stock_quantity ?? $product->stock_quantity,
            'category_id' => $request->category_id,
            'status' => $request->status ?? $product->status,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_slug' => $request->seo_slug ?? $product->seo_slug,
        ]);

        if ($request->has('images')) {
            $product->images()->delete();
            foreach ($request->images as $img) {
                $product->images()->create([
                    'image_url' => $img['image_url'],
                    'is_main' => $img['is_main'] ?? false,
                    'sort_order' => $img['sort_order'] ?? 0,
                ]);
            }
        }

        if ($request->has('variations')) {
            $product->variations()->delete();
            foreach ($request->variations as $v) {
                $product->variations()->create([
                    'attributes' => $v['attributes'],
                    'price' => $v['price'],
                    'sale_price' => $v['sale_price'] ?? null,
                    'stock_quantity' => $v['stock_quantity'] ?? 0,
                    'image_url' => $v['image_url'] ?? null,
                ]);
            }
        }

        return response()->json($product->load(['category', 'images', 'variations']));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->images()->delete();
        $product->variations()->delete();
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No product IDs provided'], 400);
        }

        $products = Product::whereIn('id', $ids)->get();
        foreach ($products as $product) {
            $product->images()->delete();
            $product->variations()->delete();
            $product->delete();
        }

        return response()->json([
            'message' => count($products) . ' products deleted successfully',
            'count' => count($products),
        ]);
    }

    public function duplicate($id)
    {
        $original = Product::with(['images', 'variations'])->findOrFail($id);
        $timestamp = time();

        $newProduct = Product::create([
            'name' => $original->name . ' (Copy)',
            'seo_slug' => $original->seo_slug . '-copy-' . $timestamp,
            'description' => $original->description,
            'product_type' => $original->product_type,
            'price' => $original->price,
            'sale_price' => $original->sale_price,
            'stock_quantity' => $original->stock_quantity,
            'category_id' => $original->category_id,
            'status' => 'inactive',
            'seo_title' => $original->seo_title,
            'seo_description' => $original->seo_description,
        ]);

        foreach ($original->images as $img) {
            $newProduct->images()->create([
                'image_url' => $img->image_url,
                'is_main' => $img->is_main,
                'sort_order' => $img->sort_order,
            ]);
        }

        foreach ($original->variations as $v) {
            $newProduct->variations()->create([
                'sku' => $v->sku ? $v->sku . '-copy-' . $timestamp : null,
                'attributes' => $v->attributes,
                'price' => $v->price,
                'sale_price' => $v->sale_price,
                'stock_quantity' => $v->stock_quantity,
                'image_url' => $v->image_url,
            ]);
        }

        return response()->json($newProduct->load(['category', 'images', 'variations']), 201);
    }
}
