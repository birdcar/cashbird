<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DebtStatus;
use App\Enums\SavingsStage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use WorkOS\AuthKit\Models\Concerns\HasWorkOSId;
use WorkOS\AuthKit\Models\Concerns\HasWorkOSPermissions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasWorkOSId, HasWorkOSPermissions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<Connection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Debt, $this> */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return HasMany<Insight, $this> */
    public function insights(): HasMany
    {
        return $this->hasMany(Insight::class);
    }

    /** @return HasOne<Budget, $this> */
    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function currentBudgetPeriod(): ?BudgetPeriod
    {
        return $this->budget?->periods()
            ->where('status', 'active')
            ->orderByDesc('month')
            ->first();
    }

    /** @return HasMany<SavingsGoal, $this> */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    /** @return HasMany<NetWorthSnapshot, $this> */
    public function netWorthSnapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    /** @return HasMany<CategoryClassification, $this> */
    public function categoryClassifications(): HasMany
    {
        return $this->hasMany(CategoryClassification::class);
    }

    public function currentSavingsStage(): SavingsStage
    {
        $hasActiveDebts = $this->debts()->where('status', DebtStatus::Active)->exists();

        $emergencyFund = $this->savingsGoals()
            ->where('is_system', true)
            ->where('name', 'Emergency Fund')
            ->first();

        $emergencyBalance = $emergencyFund?->current_balance ?? 0;

        if ($hasActiveDebts && $emergencyBalance < 100000) {
            return SavingsStage::StarterEmergencyFund;
        }

        if ($hasActiveDebts) {
            return SavingsStage::DebtPayoff;
        }

        $avgMonthlySpend = (int) abs(
            $this->transactions()
                ->where('amount', '<', 0)
                ->where('date', '>=', now()->subMonths(3))
                ->avg('amount') ?? 0
        );
        $threeMonthExpenses = max($avgMonthlySpend * 3, 100000);

        if ($emergencyBalance < $threeMonthExpenses) {
            return SavingsStage::FullEmergencyFund;
        }

        return SavingsStage::NamedGoals;
    }
}
