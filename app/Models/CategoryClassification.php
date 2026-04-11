<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BudgetCategory;
use Database\Factories\CategoryClassificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryClassification extends Model
{
    /** @use HasFactory<CategoryClassificationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'category_id',
        'classification',
        'is_ai_assigned',
    ];

    protected function casts(): array
    {
        return [
            'classification' => BudgetCategory::class,
            'is_ai_assigned' => 'boolean',
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
