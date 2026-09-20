<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['children']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_top')) {
            $query->where('is_top', filter_var($request->is_top, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories',
        ]);

        $data = $request->all();
        if (empty($data['parent_id'])) {
            $data['parent_id'] = null;
        }

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->all();
        if (array_key_exists('parent_id', $data) && empty($data['parent_id'])) {
            $data['parent_id'] = null;
        }
        $category->update($data);
        return response()->json($category);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'Category deleted']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No category IDs provided'], 400);
        }

        $count = Category::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => $count . ' categories deleted successfully',
            'count' => $count,
        ]);
    }
}