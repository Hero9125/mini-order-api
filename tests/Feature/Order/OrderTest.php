<?php

namespace Tests\Feature\Order;

use App\Jobs\ProcessOrderJob;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function actingAsUser(): static
    {
        return $this->withToken($this->user->createToken('test')->plainTextToken);
    }

    // ─── Successful order creation ────────────────────────────────────────────

    public function test_user_can_place_a_successful_order(): void
    {
        Queue::fake();

        $product = Product::factory()->inStock(50)->create(['price' => 29.99]);

        $response = $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['id', 'status', 'total_amount', 'items'],
            ]);

        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 29.99,
            'subtotal'   => 59.98,
        ]);
    }

    public function test_order_can_contain_multiple_products(): void
    {
        Queue::fake();

        $p1 = Product::factory()->inStock(20)->create(['price' => 10.00]);
        $p2 = Product::factory()->inStock(20)->create(['price' => 20.00]);

        $response = $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 3],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        // 3 × 10 + 1 × 20 = 50
        $this->assertEquals(50.00, $response->json('data.total_amount'));
    }

    // ─── Price integrity ─────────────────────────────────────────────────────

    public function test_total_amount_is_always_calculated_server_side(): void
    {
        Queue::fake();

        $product = Product::factory()->inStock(10)->create(['price' => 100.00]);

        // The request does NOT include prices — they come from DB only
        $response = $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            // Intentionally not sending price — should be ignored
        ]);

        $response->assertStatus(201);
        $this->assertEquals(100.00, $response->json('data.total_amount'));
    }

    public function test_unit_price_snapshot_is_preserved_after_product_price_change(): void
    {
        Queue::fake();

        $product = Product::factory()->inStock(10)->create(['price' => 50.00]);

        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        // Change the product price after order is placed
        $product->update(['price' => 999.00]);

        // The order item must still have the original snapshot price
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'unit_price' => 50.00,
        ]);
    }

    // ─── Stock management ────────────────────────────────────────────────────

    public function test_stock_is_reduced_after_successful_order(): void
    {
        Queue::fake();

        $product = Product::factory()->inStock(20)->create();

        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertStatus(201);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 15]);
    }

    public function test_order_fails_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->inStock(3)->create();

        $response = $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.stock.available', 3)
            ->assertJsonPath('errors.stock.requested', 10);

        // Stock must NOT be reduced on failure
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    public function test_order_is_rolled_back_if_any_item_has_insufficient_stock(): void
    {
        $p1 = Product::factory()->inStock(10)->create();
        $p2 = Product::factory()->inStock(1)->create();

        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 5], // fails
            ],
        ])->assertStatus(422);

        // p1 stock must NOT have been reduced (rollback)
        $this->assertDatabaseHas('products', ['id' => $p1->id, 'stock' => 10]);
        // No order created
        $this->assertDatabaseCount('orders', 0);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_order_fails_with_invalid_product_id(): void
    {
        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => 99999, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_order_fails_with_zero_quantity(): void
    {
        $product = Product::factory()->inStock(10)->create();

        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_order_fails_with_empty_items(): void
    {
        $this->actingAsUser()->postJson('/api/v1/orders', ['items' => []])
            ->assertStatus(422);
    }

    // ─── Listing and ownership ───────────────────────────────────────────────

    public function test_user_can_list_own_orders(): void
    {
        Queue::fake();
        // Disable throttle so the second request (GET /orders) doesn't hit 429
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $product = Product::factory()->inStock(10)->create();
        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAsUser()->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_user_cannot_see_other_users_orders(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create();

        $this->actingAsUser()
            ->getJson("/api/v1/orders/{$otherOrder->id}")
            ->assertStatus(403);
    }

    public function test_user_can_view_own_order_detail(): void
    {
        Queue::fake();
        // Disable throttle so rapid post+get doesn't hit 429
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $product = Product::factory()->inStock(10)->create();
        $createResponse = $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $orderId = $createResponse->json('data.id');

        $this->actingAsUser()
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $orderId);
    }

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        Product::factory()->inStock(10)->create();

        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ])->assertStatus(401);
    }

    // ─── Queue / Mail ─────────────────────────────────────────────────────────

    public function test_process_order_job_is_dispatched_after_successful_order(): void
    {
        Queue::fake();

        $product = Product::factory()->inStock(10)->create();

        $this->actingAsUser()->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(201);

        Queue::assertPushed(ProcessOrderJob::class);
    }

    public function test_order_confirmation_email_is_sent(): void
    {
        Mail::fake();

        $product = Product::factory()->inStock(10)->create();
        $order = Order::factory()->for($this->user)->create(['total_amount' => 100]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => 100.00,
            'subtotal'   => 100.00,
        ]);
        $order->load(['user', 'items.product']);

        Mail::to($this->user->email)->send(new OrderConfirmationMail($order));

        Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }
}
