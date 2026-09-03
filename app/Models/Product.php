<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'sku',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────────────────

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Scope for full-text search on name.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText('name', $term);
    }

    /**
     * Scope to filter products with available stock.
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
