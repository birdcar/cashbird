<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecurringChargeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringCharge extends Model
{
    /** @use HasFactory<RecurringChargeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'merchant_name',
        'category_id',
        'average_amount',
        'frequency',
        'confidence',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'average_amount' => 'integer',
            'confidence' => 'float',
            'last_seen_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
