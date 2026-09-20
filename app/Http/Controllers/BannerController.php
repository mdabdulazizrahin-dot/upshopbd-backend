<?php
namespace App\Http\Controllers;
use App\Models\Banner;
use Illuminate\Http\Request;
class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->get()->map(function ($banner) {
            $banner->is_active = $banner->status === 'active';
            return $banner;
        });
        return response()->json($banners);
    }

    public function store(Request $request)
    {
        $data = $request->except('is_active');
        $data['status'] = $request->input('is_active', true) ? 'active' : 'inactive';
        $banner = Banner::create($data);
        $banner->is_active = $banner->status === 'active';
        return response()->json($banner, 201);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->except('is_active');
        if ($request->has('is_active')) {
            $data['status'] = $request->input('is_active') ? 'active' : 'inactive';
        }
        $banner->update($data);
        $banner->is_active = $banner->status === 'active';
        return response()->json($banner);
    }

    public function destroy($id)
    {
        Banner::findOrFail($id)->delete();
        return response()->json(['message' => 'Banner deleted']);
    }
}
