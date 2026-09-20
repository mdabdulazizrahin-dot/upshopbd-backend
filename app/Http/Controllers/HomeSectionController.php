<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use Illuminate\Http\Request;

class HomeSectionController extends Controller
{
    public function index()
    {
        return response()->json(HomeSection::orderBy('sort_order')->get());
    }

    public function update(Request $request, $id)
    {
        $section = HomeSection::findOrFail($id);
        $updates = $request->input('updates', $request->all());

        $data = [];
        if (array_key_exists('title', $updates)) $data['title'] = $updates['title'];
        if (array_key_exists('is_visible', $updates)) $data['is_visible'] = (bool)$updates['is_visible'];
        if (array_key_exists('sort_order', $updates)) $data['sort_order'] = (int)$updates['sort_order'];
        if (array_key_exists('settings', $updates)) $data['settings'] = $updates['settings'];

        $section->update($data);
        return response()->json($section);
    }

    public function store(Request $request)
    {
        if ($request->has('reorder')) {
            $reorderList = $request->input('reorder');
            foreach ($reorderList as $item) {
                if (isset($item['id']) && isset($item['sort_order'])) {
                    HomeSection::where('id', $item['id'])->update(['sort_order' => (int)$item['sort_order']]);
                }
            }
            return response()->json(HomeSection::orderBy('sort_order')->get());
        }

        $id = $request->input('id');
        $updates = $request->input('updates', $request->all());

        if ($id) {
            $section = HomeSection::findOrFail($id);
            $data = [];
            if (array_key_exists('title', $updates)) $data['title'] = $updates['title'];
            if (array_key_exists('is_visible', $updates)) $data['is_visible'] = (bool)$updates['is_visible'];
            if (array_key_exists('sort_order', $updates)) $data['sort_order'] = (int)$updates['sort_order'];
            if (array_key_exists('settings', $updates)) $data['settings'] = $updates['settings'];

            $section->update($data);
            return response()->json($section);
        }

        $section = HomeSection::create($request->only(['section_key', 'title', 'is_visible', 'sort_order', 'settings']));
        return response()->json($section, 201);
    }
}
