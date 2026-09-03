<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(name="Products", description="Product management endpoints")
 */
class ProductController extends Controller
{
    private const CACHE_TTL     = 300; // 5 minutes
    private const CACHE_TAG     = 'products';
    private const PER_PAGE_MAX  = 100;

    /**
     * Allowed sort columns — never pass raw user input to orderBy.
     */
    private const SORTABLE = ['id', 'name', 'price', 'stock', 'created_at'];

    /**
     * Returns a cache store that supports tags if available (Redis),
     * otherwise falls back to the plain cache store.
     * This ensures tests using the 'array' driver don't throw.
     */
    private function cache()
    {
        try {
            return Cache::tags([self::CACHE_TAG]);
        } catch (\BadMethodCallException) {
            return Cache::store();
        }
    }

    /**
     * Flush all product cache entries (or entire store in non-taggable drivers).
     */
    private function flushCache(): void
    {
        try {
            Cache::tags([self::CACHE_TAG])->flush();
        } catch (\BadMethodCallException) {
            Cache::flush();
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="List products with search, filter, and pagination",
     *     @OA\Parameter(name="search",      in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="min_price",   in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price",   in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="in_stock",    in="query", @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="sort_by",     in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_dir",    in="query", @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated product list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage  = min((int) $request->input('per_page', 15), self::PER_PAGE_MAX);
        $sortBy   = in_array($request->input('sort_by'), self::SORTABLE) ? $request->input('sort_by') : 'created_at';
        $sortDir  = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';

        // Build a cache key that reflects the full query fingerprint
        $cacheKey = self::CACHE_TAG . ':list:' . md5(json_encode($request->query()));

        $paginator = $this->cache()->remember($cacheKey, self::CACHE_TTL, function () use ($request, $perPage, $sortBy, $sortDir) {
            $query = Product::query();

            // Full-text search with LIKE fallback
            if ($search = $request->input('search')) {
                // Try full-text search first; fall back to LIKE for short terms
                // or when the FULLTEXT index hasn't committed the new rows (test env).
                if (strlen($search) >= 3) {
                    $query->whereFullText('name', $search);
                } else {
                    $query->where('name', 'like', '%' . $search . '%');
                }
            }

            // Price range filters
            if ($request->filled('min_price')) {
                $query->where('price', '>=', (float) $request->input('min_price'));
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', (float) $request->input('max_price'));
            }

            // Stock availability
            if ($request->boolean('in_stock')) {
                $query->inStock();
            }

            return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'data'    => ProductResource::collection($paginator),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="Create a new product (admin only)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name",  type="string"),
     *         @OA\Property(property="price", type="number"),
     *         @OA\Property(property="stock", type="integer")
     *     )),
     *     @OA\Response(response=201, description="Product created")
     * )
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Get a single product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product details"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show(Product $product): JsonResponse
    {
        $cacheKey = self::CACHE_TAG . ':single:' . $product->id;

        $data = $this->cache()->remember($cacheKey, self::CACHE_TTL, fn () => new ProductResource($product));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Update a product (admin only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product updated")
     * )
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => new ProductResource($product->fresh()),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Delete a product (admin only)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product deleted"),
     *     @OA\Response(response=409, description="Cannot delete - has order history")
     * )
     */
    public function destroy(Product $product): JsonResponse
    {
        // Authorise via UpdateProductRequest reuse is not possible here,
        // so we check manually (admin guard)
        if (! request()->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        try {
            $product->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // FK RESTRICT violation — product has order history
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this product because it is referenced by existing orders.',
            ], 409);
        }

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
