<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NetWorthSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetWorthSnapshot extends Model
{
    /** @use HasFactory<NetWorthSnapshotFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'month',
        'total_assets',
        'total_debts',
        'net_worth',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'total_assets' => 'integer',
            'total_debts' => 'integer',
            'net_worth' => 'integer',
            'breakdown' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
