<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatched after a successful order transaction commit.
 *
 * Retry-safety: Mail::send is idempotent (worst case: duplicate email).
 * We do NOT put stock deduction here — that lives in the synchronous transaction.
 */
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Retry delays in seconds (exponential back-off: 60s, 120s, 240s).
     */
    public function backoff(): array
    {
        return [60, 120, 240];
    }

    /**
     * Delete the job if the Order model no longer exists
     * (e.g., order was cancelled/deleted before job ran).
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Order $order)
    {
    }

    public function handle(): void
    {
        // Reload the order with relationships to ensure fresh data.
        $this->order->loadMissing(['user', 'items.product']);

        try {
            Mail::to($this->order->user->email)
                ->send(new OrderConfirmationMail($this->order));
        } catch (\Throwable $e) {
            Log::error('OrderConfirmationMail failed', [
                'order_id' => $this->order->id,
                'error'    => $e->getMessage(),
            ]);
            // Re-throw so the queue driver can retry the job.
            throw $e;
        }
    }

    /**
     * Handle final failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessOrderJob permanently failed', [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}
