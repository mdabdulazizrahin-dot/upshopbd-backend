<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        return response()->json(Page::orderBy('created_at', 'desc')->get());
    }

    public function show($slug)
    {
        $page = Page::where('slug', $slug)->first();
        if (!$page) return response()->json(['error' => 'Not found'], 404);
        return response()->json($page);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
        ]);

        $data = [
            'title' => $request->title,
            'content' => $request->content,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'image_url' => $request->image_url,
            'page_type' => $request->page_type ?? 'page',
            'status' => $request->status ?? 'published',
        ];

        // If updating by id
        if ($request->id) {
            $page = Page::findOrFail($request->id);
            $page->update(array_merge($data, ['slug' => $request->slug]));
            return response()->json($page);
        }

        $page = Page::updateOrCreate(
            ['slug' => $request->slug],
            $data
        );

        return response()->json($page);
    }

    public function destroy($id)
    {
        $page = Page::findOrFail($id);
        $page->delete();
        return response()->json(['message' => 'Page deleted successfully']);
    }
}