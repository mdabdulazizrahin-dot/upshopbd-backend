<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleSheetSyncController extends Controller
{
    /**
     * Get saved Google Sheet URL & sync metadata.
     */
    public function getSettings()
    {
        $setting = SiteSetting::where('key', 'google_sheet_sync')->first();
        $data = $setting ? json_decode($setting->value, true) : [];

        return response()->json([
            'success' => true,
            'sheet_url' => $data['sheet_url'] ?? '',
            'last_synced_at' => $data['last_synced_at'] ?? null,
            'last_synced_count' => $data['last_synced_count'] ?? 0,
        ]);
    }

    /**
     * Preview rows from Google Sheet or CSV before syncing.
     */
    public function preview(Request $request)
    {
        $sheetUrl = $request->input('sheet_url');
        $csvText = $request->input('csv_text');

        if ($request->hasFile('csv_file')) {
            $csvText = file_get_contents($request->file('csv_file')->getRealPath());
        }

        if (empty($csvText) && !empty($sheetUrl)) {
            $csvUrl = $this->convertToCsvUrl($sheetUrl);
            $csvText = $this->fetchCsvContent($csvUrl);
            if (!$csvText) {
                return response()->json([
                    'success' => false,
                    'message' => 'গুগল শিট থেকে ডাটা পড়তে ব্যর্থ হয়েছে। নিশ্চিত করুন শিটটি "Anyone with the link can view" আকারে শেয়ার করা আছে।',
                ], 422);
            }
        }

        if (empty($csvText)) {
            return response()->json([
                'success' => false,
                'message' => 'অনুগ্রহ করে একটি গুগল শিটের লিংক দিন অথবা CSV ফাইল দিন।',
            ], 422);
        }

        $parsed = $this->parseCsv($csvText);
        if (empty($parsed['rows'])) {
            return response()->json([
                'success' => false,
                'message' => 'শিটে কোনো প্রোডাক্টের তথ্য খুঁজে পাওয়া যায়নি। হেডার কলামগুলো (name, price ইত্যাদি) ঠিক আছে কি না চেক করুন।',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'total_rows' => count($parsed['rows']),
            'headers' => $parsed['headers'],
            'preview' => array_slice($parsed['rows'], 0, 8),
        ]);
    }

    /**
     * Sync and import products into database.
     */
    public function sync(Request $request)
    {
        $sheetUrl = $request->input('sheet_url');
        $csvText = $request->input('csv_text');
        $items = $request->input('items');

        if ($request->hasFile('csv_file')) {
            $csvText = file_get_contents($request->file('csv_file')->getRealPath());
        }

        if (empty($items)) {
            if (empty($csvText) && !empty($sheetUrl)) {
                $csvUrl = $this->convertToCsvUrl($sheetUrl);
                $csvText = $this->fetchCsvContent($csvUrl);
                if (!$csvText) {
                    return response()->json([
                        'success' => false,
                        'message' => 'গুগল শিট থেকে ডাটা পড়তে ব্যর্থ হয়েছে। শিটটির শেয়ারিং পারমিশন "Anyone with the link can view" কিনা যাচাই করুন।',
                    ], 422);
                }
            }

            if (empty($csvText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'কোনো ডাটা পাওয়া যায়নি।',
                ], 422);
            }

            $parsed = $this->parseCsv($csvText);
            $items = $parsed['rows'];
        }

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'কোনো ভ্যালিড প্রোডাক্ট পাওয়া যায়নি।',
            ], 422);
        }

        $createdCount = 0;
        $updatedCount = 0;
        $syncedProducts = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $name = trim($item['name'] ?? '');
                if (empty($name)) continue;

                $price = floatval($item['price'] ?? 0);
                $salePrice = !empty($item['sale_price']) ? floatval($item['sale_price']) : null;
                $stock = isset($item['stock_quantity']) && $item['stock_quantity'] !== '' ? intval($item['stock_quantity']) : 100;
                $description = $item['description'] ?? null;
                $status = !empty($item['status']) && in_array(strtolower($item['status']), ['active', 'inactive']) ? strtolower($item['status']) : 'active';

                // Find or create Category
                $categoryId = null;
                if (!empty($item['category'])) {
                    $catName = trim($item['category']);
                    $catSlug = Str::slug($catName);
                    $category = Category::where('name', $catName)
                        ->orWhere('slug', $catSlug)
                        ->first();

                    if (!$category) {
                        $category = Category::create([
                            'name' => $catName,
                            'name_bn' => $catName,
                            'slug' => $catSlug ?: 'cat-' . time() . '-' . rand(10, 99),
                            'status' => 'active',
                            'is_top' => false,
                        ]);
                    }
                    $categoryId = $category->id;
                }

                // Check if product exists by name or slug
                $existingProduct = Product::where('name', $name)->first();

                if ($existingProduct) {
                    $existingProduct->update([
                        'price' => $price > 0 ? $price : $existingProduct->price,
                        'sale_price' => $salePrice,
                        'stock_quantity' => $stock,
                        'description' => $description ?: $existingProduct->description,
                        'category_id' => $categoryId ?: $existingProduct->category_id,
                        'status' => $status,
                    ]);

                    $product = $existingProduct;
                    $updatedCount++;
                } else {
                    $baseSlug = Str::slug($name);
                    $slug = $baseSlug ?: 'prod-' . time();
                    if (Product::where('seo_slug', $slug)->exists()) {
                        $slug = $slug . '-' . time() . '-' . rand(10, 99);
                    }

                    $product = Product::create([
                        'name' => $name,
                        'description' => $description,
                        'product_type' => 'simple',
                        'price' => $price,
                        'sale_price' => $salePrice,
                        'stock_quantity' => $stock,
                        'category_id' => $categoryId,
                        'status' => $status,
                        'seo_title' => $name,
                        'seo_description' => Str::limit(strip_tags($description ?? ''), 160),
                        'seo_slug' => $slug,
                    ]);
                    $createdCount++;
                }

                // Handle Images
                if (!empty($item['image_url'])) {
                    $images = preg_split('/[,\|\n]+/', $item['image_url']);
                    $sortOrder = 0;
                    foreach ($images as $imgUrl) {
                        $imgUrl = trim($imgUrl);
                        if (empty($imgUrl)) continue;

                        $hasImg = ProductImage::where('product_id', $product->id)
                            ->where('image_url', $imgUrl)
                            ->exists();

                        if (!$hasImg) {
                            $isMain = ($sortOrder === 0 && ProductImage::where('product_id', $product->id)->count() === 0);
                            ProductImage::create([
                                'product_id' => $product->id,
                                'image_url' => $imgUrl,
                                'is_main' => $isMain,
                                'sort_order' => $sortOrder++,
                            ]);
                        }
                    }
                }

                $syncedProducts[] = $product->load(['category', 'images']);
            }

            // Save sheet URL & last sync timestamp into SiteSetting
            if (!empty($sheetUrl)) {
                SiteSetting::updateOrCreate(
                    ['key' => 'google_sheet_sync'],
                    ['value' => json_encode([
                        'sheet_url' => $sheetUrl,
                        'last_synced_at' => now()->toIso8601String(),
                        'last_synced_count' => count($syncedProducts),
                    ])]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "সফলভাবে " . count($syncedProducts) . " টি প্রোডাক্ট সিঙ্ক করা হয়েছে! (নতুন যুক্ত: {$createdCount}, আপডেট: {$updatedCount})",
                'synced_count' => count($syncedProducts),
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'products' => array_slice($syncedProducts, 0, 20),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'প্রোডাক্ট সিঙ্ক করার সময় সমস্যা হয়েছে: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert Google Sheet URL to direct CSV Export URL.
     */
    private function convertToCsvUrl(string $url): string
    {
        $url = trim($url);

        // If already a direct CSV URL
        if (str_contains($url, 'export?format=csv') || str_contains($url, 'tqx=out:csv')) {
            return $url;
        }

        // Extract Google Sheet ID
        if (preg_match('/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $sheetId = $matches[1];
            
            // Extract gid if exists
            $gid = '0';
            if (preg_match('/[#&?]gid=([0-9]+)/', $url, $gidMatches)) {
                $gid = $gidMatches[1];
            }

            return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
        }

        return $url;
    }

    /**
     * Fetch CSV content with timeout and user agent.
     */
    private function fetchCsvContent(string $url): ?string
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) UpShopBD-Sync/1.0',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $content = @file_get_contents($url, false, $ctx);
            return $content ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parse CSV text into array of associative product rows with flexible header matching.
     */
    private function parseCsv(string $csvText): array
    {
        // Normalize line endings
        $csvText = str_replace(["\r\n", "\r"], "\n", $csvText);
        $lines = explode("\n", trim($csvText));
        if (count($lines) < 2) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = str_getcsv(array_shift($lines));
        $mappedHeaders = [];

        foreach ($headerRow as $index => $rawHeader) {
            $clean = strtolower(trim($rawHeader));
            $clean = str_replace([' ', '_', '-'], '', $clean);

            if (in_array($clean, ['name', 'productname', 'title', 'product', 'নাম', 'পণ্যেরনাম'])) {
                $mappedHeaders[$index] = 'name';
            } elseif (in_array($clean, ['price', 'regularprice', 'originalprice', 'মূল্য', 'দাম'])) {
                $mappedHeaders[$index] = 'price';
            } elseif (in_array($clean, ['saleprice', 'offerprice', 'discountprice', 'অফারমূল্য', 'ডিসকাউন্টদাম'])) {
                $mappedHeaders[$index] = 'sale_price';
            } elseif (in_array($clean, ['category', 'categoryname', 'cat', 'ক্যাটাগরি'])) {
                $mappedHeaders[$index] = 'category';
            } elseif (in_array($clean, ['stock', 'stockquantity', 'quantity', 'qty', 'স্টক', 'পরিমাণ'])) {
                $mappedHeaders[$index] = 'stock_quantity';
            } elseif (in_array($clean, ['description', 'desc', 'details', 'বিবরণ', 'বিস্তারিত'])) {
                $mappedHeaders[$index] = 'description';
            } elseif (in_array($clean, ['image', 'imageurl', 'images', 'photo', 'picture', 'ছবি', 'ছবিরলিংক'])) {
                $mappedHeaders[$index] = 'image_url';
            } elseif (in_array($clean, ['status', 'active', 'স্ট্যাটাস'])) {
                $mappedHeaders[$index] = 'status';
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $rowValues = str_getcsv($line);
            $row = [
                'name' => '',
                'price' => 0,
                'sale_price' => null,
                'category' => '',
                'stock_quantity' => 100,
                'description' => '',
                'image_url' => '',
                'status' => 'active',
            ];

            $hasAnyValue = false;
            foreach ($rowValues as $index => $val) {
                if (isset($mappedHeaders[$index])) {
                    $key = $mappedHeaders[$index];
                    $val = trim($val ?? '');
                    if ($val !== '') $hasAnyValue = true;
                    $row[$key] = $val;
                }
            }

            if ($hasAnyValue && !empty($row['name'])) {
                $rows[] = $row;
            }
        }

        return [
            'headers' => array_values(array_unique($mappedHeaders)),
            'rows' => $rows,
        ];
    }
}
