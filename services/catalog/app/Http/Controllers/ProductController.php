<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with([
                'categories:id,name,slug',
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'inventory',
            ])
            ->where('status', 'active');

        $useRelevanceSort = false;

        if ($search = $request->query('search')) {
            $connection = $query->getModel()->getConnection();

            if ($connection->getDriverName() === 'mysql') {
                // Use FULLTEXT search in BOOLEAN MODE on MySQL
                $query->select('products.*')
                    ->selectRaw('MATCH(name, description) AGAINST (? IN BOOLEAN MODE) AS relevance_score', [$search])
                    ->whereRaw('MATCH(name, description) AGAINST (? IN BOOLEAN MODE)', [$search]);

                $useRelevanceSort = true;
            } else {
                // Fallback for other drivers (SQLite, etc.)
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        }

        if ($category = $request->query('category')) {
            $query->whereHas('categories', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        $sort = $request->query('sort');

        if ($sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sort === 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($useRelevanceSort && ($sort === null || $sort === 'relevance')) {
            $query->orderByDesc('relevance_score');
        } else {
            $query->orderBy('name');
        }

        $perPage = (int) $request->query('per_page', 12);
        $perPage = max(1, min($perPage, 50));

        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    public function show(string $slug)
    {
        $product = Product::query()
            ->with([
                'categories:id,name,slug',
                'images' => fn ($q) => $q->orderBy('sort_order'),
                'inventory',
            ])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return response()->json($product);
    }
}
