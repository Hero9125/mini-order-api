<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user  = User::factory()->create();
    }

    // ─── List ────────────────────────────────────────────────────────────────

    public function test_anyone_can_list_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'data', 'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_products_are_paginated(): void
    {
        Product::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/products?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_products_can_be_filtered_by_price_range(): void
    {
        Product::factory()->create(['price' => 10.00, 'name' => 'Cheap Item']);
        Product::factory()->create(['price' => 500.00, 'name' => 'Expensive Item']);

        $response = $this->getJson('/api/v1/products?max_price=50');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Cheap Item', $response->json('data.0.name'));
    }

    public function test_products_can_be_filtered_by_in_stock(): void
    {
        Product::factory()->inStock(10)->create();
        Product::factory()->outOfStock()->create();

        $response = $this->getJson('/api/v1/products?in_stock=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.in_stock'));
    }

    // ─── Search ──────────────────────────────────────────────────────────────

    public function test_products_can_be_searched_by_name(): void
    {
        // Use a 2-char search term so we exercise the LIKE fallback path
        // (MySQL FULLTEXT sync may lag inside RefreshDatabase transactions).
        Product::factory()->create(['name' => 'WB Mechanical Keyboard']);
        Product::factory()->create(['name' => 'Gaming Optical Mouse']);

        // 'WB' is 2 chars => triggers the LIKE path
        $response = $this->getJson('/api/v1/products?search=WB');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $found = collect($names)->contains(fn ($n) => str_contains(strtolower($n), 'wb'));
        $this->assertTrue($found, 'Expected to find a product matching "WB" via LIKE search');
    }

    // ─── Show ────────────────────────────────────────────────────────────────

    public function test_anyone_can_get_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['id' => $product->id, 'name' => $product->name],
            ]);
    }

    public function test_returns_404_for_non_existent_product(): void
    {
        $this->getJson('/api/v1/products/99999')->assertStatus(404);
    }

    // ─── Create ──────────────────────────────────────────────────────────────

    public function test_admin_can_create_product(): void
    {
        $response = $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->postJson('/api/v1/products', [
                'name'  => 'New Product',
                'price' => 49.99,
                'stock' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'data' => ['name' => 'New Product']]);

        $this->assertDatabaseHas('products', ['name' => 'New Product', 'price' => 49.99]);
    }

    public function test_non_admin_cannot_create_product(): void
    {
        $this->withToken($this->user->createToken('t')->plainTextToken)
            ->postJson('/api/v1/products', [
                'name'  => 'Forbidden Product',
                'price' => 10.00,
                'stock' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_product_creation_fails_with_negative_price(): void
    {
        $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->postJson('/api/v1/products', [
                'name'  => 'Bad Product',
                'price' => -5.00,
                'stock' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_product_creation_fails_with_negative_stock(): void
    {
        $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->postJson('/api/v1/products', [
                'name'  => 'Bad Product',
                'price' => 10.00,
                'stock' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create(['price' => 10.00]);

        $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->putJson("/api/v1/products/{$product->id}", ['price' => 25.00])
            ->assertStatus(200)
            ->assertJson(['data' => ['price' => 25.00]]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 25.00]);
    }

    public function test_non_admin_cannot_update_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->user->createToken('t')->plainTextToken)
            ->putJson("/api/v1/products/{$product->id}", ['price' => 1.00])
            ->assertStatus(403);
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_non_admin_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->user->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertStatus(403);
    }

    // ─── Cache invalidation ──────────────────────────────────────────────────

    public function test_cache_is_invalidated_after_product_update(): void
    {
        // Disable throttle middleware so rapid back-to-back requests don't hit 429.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // In test env, CACHE_DRIVER=array. Tags are not supported by the array driver,
        // so Cache::tags()->flush() degrades gracefully. We verify that after an update
        // the API response returns the fresh DB value (cache is bypassed or cleared).
        $product = Product::factory()->create(['price' => 10.00]);

        // Warm the cache with first request
        $first = $this->getJson("/api/v1/products/{$product->id}");
        $first->assertStatus(200);
        $this->assertEquals(10.00, $first->json('data.price'));

        // Update price via admin
        $this->withToken($this->admin->createToken('t')->plainTextToken)
            ->putJson("/api/v1/products/{$product->id}", ['price' => 99.00])
            ->assertStatus(200);

        // Verify DB was updated
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 99.00]);

        // Fetch again — must reflect the new price (cache cleared by flush)
        $second = $this->getJson("/api/v1/products/{$product->id}");
        $second->assertStatus(200);
        $this->assertEquals(99.00, $second->json('data.price'));
    }
}
