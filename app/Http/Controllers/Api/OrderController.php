<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Orders", description="Order management endpoints")
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="Place a new order",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="items", type="array",
     *             @OA\Items(
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity",   type="integer", example=2)
     *             )
     *         ),
     *         @OA\Property(property="notes", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=422, description="Validation error or insufficient stock")
     * )
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            user:  $request->user(),
            items: $request->input('items'),
            notes: $request->input('notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data'    => new OrderResource($order),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="List authenticated user's orders",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Order list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with('items')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => OrderResource::collection($orders),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
                'last_page'    => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}",
     *     tags={"Orders"},
     *     summary="Get order details",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        // Users can only view their own orders — ownership check.
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this order.',
            ], 403);
        }

        $order->load(['items.product', 'user']);

        return response()->json([
            'success' => true,
            'data'    => new OrderResource($order),
        ]);
    }
}
