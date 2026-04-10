<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentSource;
use Database\Factories\DebtPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    /** @use HasFactory<DebtPaymentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'debt_id',
        'amount',
        'principal',
        'interest',
        'balance_after',
        'payment_date',
        'source',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'principal' => 'integer',
            'interest' => 'integer',
            'balance_after' => 'integer',
            'payment_date' => 'date',
            'source' => PaymentSource::class,
        ];
    }

    /** @return BelongsTo<Debt, $this> */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
