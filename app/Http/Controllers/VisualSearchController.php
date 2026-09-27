<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class VisualSearchController extends Controller
{
    /**
     * Search products by an uploaded image.
     */
    public function search(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:8192',
        ]);

        $file = $request->file('image');
        $uploadedPath = $file->getRealPath();
        $uploadedOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $queryContent = @file_get_contents($uploadedPath);

        // Compute visual signature of query image
        $queryDHash = $this->computeDHashFromContent($queryContent);
        $queryColors = $this->computeAvgColorFromContent($queryContent);

        // Store temporarily for preview in response
        $tempPath = $file->store('temp_search', 'public');
        $queryImageUrl = asset('storage/' . $tempPath);

        // Fetch active products with images
        $products = Product::with(['category', 'images', 'variations'])
            ->where('status', 'active')
            ->get();

        $rankedResults = [];

        foreach ($products as $product) {
            $bestScore = 0;

            if ($product->images && count($product->images) > 0) {
                foreach ($product->images as $img) {
                    $imgUrl = $img->image_url;
                    if (empty($imgUrl)) continue;

                    // Direct filename match bonus (e.g. if user uploads the product's image directly)
                    $imgFilename = pathinfo(basename($imgUrl), PATHINFO_FILENAME);
                    if (!empty($uploadedOriginalName) && !empty($imgFilename) && 
                        (strcasecmp($uploadedOriginalName, $imgFilename) === 0 || str_contains($imgUrl, $uploadedOriginalName))) {
                        $bestScore = 1.0;
                        break;
                    }

                    $imgContent = $this->getImageContent($imgUrl);
                    if ($imgContent) {
                        $productDHash = $this->computeDHashFromContent($imgContent);
                        $productColors = $this->computeAvgColorFromContent($imgContent);

                        if ($queryDHash && $productDHash) {
                            $dist = $this->hammingDistance($queryDHash, $productDHash);
                            $hashSim = max(0, 1 - ($dist / 64)); // 0 to 1

                            // Color similarity
                            $colorSim = $this->colorSimilarity($queryColors, $productColors);

                            $combined = ($hashSim * 0.70) + ($colorSim * 0.30);
                            if ($combined > $bestScore) {
                                $bestScore = $combined;
                            }
                        }
                    }
                }
            }

            // Only consider meaningful matches (score > 0.15)
            if ($bestScore > 0.15) {
                // Scale score to an intuitive percentage (between 60% and 99%)
                $matchPercentage = round(60 + ($bestScore * 39));

                $rankedResults[] = [
                    'product' => $product,
                    'match_percentage' => min(99, $matchPercentage),
                    'raw_score' => $bestScore,
                ];
            }
        }

        // If no strict matches found, but we have products, fallback to top color matches
        if (empty($rankedResults)) {
            foreach ($products as $product) {
                if ($product->images && count($product->images) > 0) {
                    $imgUrl = $product->images[0]->image_url;
                    $imgContent = $this->getImageContent($imgUrl);
                    if ($imgContent) {
                        $productColors = $this->computeAvgColorFromContent($imgContent);
                        $colorSim = $this->colorSimilarity($queryColors, $productColors);
                        if ($colorSim > 0.65) {
                            $rankedResults[] = [
                                'product' => $product,
                                'match_percentage' => round(50 + ($colorSim * 30)),
                                'raw_score' => $colorSim * 0.5,
                            ];
                        }
                    }
                }
            }
        }

        // Sort descending by match score
        usort($rankedResults, function ($a, $b) {
            return $b['raw_score'] <=> $a['raw_score'];
        });

        // Top 16 matches
        $topMatches = array_slice($rankedResults, 0, 16);

        return response()->json([
            'success' => true,
            'query_image_url' => $queryImageUrl,
            'total_found' => count($topMatches),
            'results' => $topMatches,
        ]);
    }

    /**
     * Get image content whether local or remote.
     */
    private function getImageContent($urlOrPath)
    {
        if (empty($urlOrPath)) return null;

        $local = $this->resolveLocalImagePath($urlOrPath);
        if ($local && file_exists($local)) {
            return @file_get_contents($local);
        }

        if (file_exists($urlOrPath)) {
            return @file_get_contents($urlOrPath);
        }

        if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2.5,
                    'user_agent' => 'UpShopBD-VisualSearch/1.0',
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            return @file_get_contents($urlOrPath, false, $ctx);
        }

        return null;
    }

    /**
     * Compute 64-bit Difference Hash (dHash) using GD.
     */
    private function computeDHashFromContent($content)
    {
        try {
            if (!$content) return null;

            $src = @imagecreatefromstring($content);
            if (!$src) return null;

            // Resize to 9x8 for difference comparisons
            $resized = imagecreatetruecolor(9, 8);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));
            imagefilter($resized, IMG_FILTER_GRAYSCALE);

            $hash = '';
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $leftColor = imagecolorat($resized, $x, $y) & 0xFF;
                    $rightColor = imagecolorat($resized, $x + 1, $y) & 0xFF;
                    $hash .= ($leftColor > $rightColor) ? '1' : '0';
                }
            }

            imagedestroy($src);
            imagedestroy($resized);

            return $hash;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compute average RGB color vector of an image.
     */
    private function computeAvgColorFromContent($content)
    {
        try {
            if (!$content) return ['r' => 128, 'g' => 128, 'b' => 128];

            $src = @imagecreatefromstring($content);
            if (!$src) return ['r' => 128, 'g' => 128, 'b' => 128];

            $thumb = imagecreatetruecolor(1, 1);
            imagecopyresampled($thumb, $src, 0, 0, 0, 0, 1, 1, imagesx($src), imagesy($src));
            $rgb = imagecolorat($thumb, 0, 0);

            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            imagedestroy($src);
            imagedestroy($thumb);

            return ['r' => $r, 'g' => $g, 'b' => $b];
        } catch (\Throwable $e) {
            return ['r' => 128, 'g' => 128, 'b' => 128];
        }
    }

    /**
     * Hamming distance between two binary strings.
     */
    private function hammingDistance($str1, $str2)
    {
        $dist = 0;
        $len = min(strlen($str1), strlen($str2));
        for ($i = 0; $i < $len; $i++) {
            if ($str1[$i] !== $str2[$i]) {
                $dist++;
            }
        }
        return $dist + abs(strlen($str1) - strlen($str2));
    }

    /**
     * Color similarity (0 to 1) based on Euclidean distance in RGB space.
     */
    private function colorSimilarity($c1, $c2)
    {
        if (!$c1 || !$c2) return 0.5;
        $deltaR = $c1['r'] - $c2['r'];
        $deltaG = $c1['g'] - $c2['g'];
        $deltaB = $c1['b'] - $c2['b'];
        $distance = sqrt(($deltaR * $deltaR) + ($deltaG * $deltaG) + ($deltaB * $deltaB));
        $maxDist = sqrt(3 * (255 * 255)); // ~441.67
        return max(0, 1 - ($distance / $maxDist));
    }

    /**
     * Resolve image URL or path to local disk path.
     */
    private function resolveLocalImagePath($url)
    {
        if (empty($url)) return null;

        if (str_contains($url, '/storage/')) {
            $parts = explode('/storage/', $url);
            $relativePath = end($parts);
            $fullPath = public_path('storage/' . $relativePath);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
            $storagePath = storage_path('app/public/' . $relativePath);
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return null;
    }
}
