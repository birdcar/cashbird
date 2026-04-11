<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'period_month',
        'title',
        'content',
        'summary',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
