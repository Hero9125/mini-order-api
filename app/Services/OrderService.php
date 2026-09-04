<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * OrderService encapsulates the order creation business logic.
 *
 * Why a Service here?
 * The creation flow involves multiple models, a DB transaction, pessimistic locking,
 * stock validation, price calculation, and job dispatch — too complex for a controller.
 * Keeping it in a Service makes it testable in isolation and easy to understand.
 */
class OrderService
{
    /**
     * Create an order atomically.
     *
     * Transaction + locking strategy (interview-ready explanation):
     *
     * 1.  We open a DB transaction.
     * 2.  For each ordered product we issue SELECT ... FOR UPDATE (lockForUpdate).
     *     This places an exclusive row-level lock in InnoDB, preventing any other
     *     transaction from reading or modifying those rows until we commit/rollback.
     * 3.  We re-read the stock AFTER acquiring the lock (not before). This is the
     *     critical step — reading before the lock is a TOCTOU bug.
     * 4.  If stock is insufficient we throw InsufficientStockException which
     *     propagates out of the closure and triggers an automatic rollback.
     * 5.  On success the stock decrement, order creation, and order-item creation
     *     all commit together atomically.
     * 6.  The job is dispatched AFTER the commit, outside the transaction.
     *     Dispatching inside the transaction could cause the job to run before the
     *     data is visible to other connections.
     *
     * @param  User   $user    Authenticated user — never trust client-provided user_id
     * @param  array  $items   Validated items: [['product_id' => int, 'quantity' => int], ...]
     * @param  string|null $notes
     * @return Order
     */
    public function createOrder(User $user, array $items, ?string $notes = null): Order
    {
        $order = DB::transaction(function () use ($user, $items, $notes) {

            $totalAmount = 0.0;
            $orderItemsData = [];

            foreach ($items as $item) {
                // Acquire an exclusive row-level lock on the product row.
                // Any concurrent transaction trying to lock the same row blocks here
                // until this transaction commits or rolls back.
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                // Stock check AFTER acquiring the lock (safe from race conditions).
                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException($product, $item['quantity']);
                }

                // Price comes from DB, never from the request.
                $unitPrice = (float) $product->price;
                $subtotal  = round($unitPrice * $item['quantity'], 2);
                $totalAmount += $subtotal;

                // Atomically reduce stock.
                $product->decrement('stock', $item['quantity']);

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $unitPrice,   // historical snapshot
                    'subtotal'   => $subtotal,
                ];
            }

            // Create the order — total calculated server-side.
            $order = Order::create([
                'user_id'      => $user->id,
                'status'       => 'pending',
                'total_amount' => round($totalAmount, 2),
                'notes'        => $notes,
            ]);

            // Bulk-insert order items.
            $now = now();
            $order->items()->createMany(
                array_map(fn ($row) => array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]), $orderItemsData)
            );

            return $order;
        }); // ← transaction commits here

        // Dispatch post-order job only after successful commit.
        ProcessOrderJob::dispatch($order)->afterCommit();

        return $order->load(['items.product', 'user']);
    }
}
