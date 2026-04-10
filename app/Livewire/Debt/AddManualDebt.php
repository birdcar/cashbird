<?php

declare(strict_types=1);

namespace App\Livewire\Debt;

use App\Models\Debt;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AddManualDebt extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:credit_card,payday_loan,student_loan,personal_loan,auto_loan,mortgage,recovery_plan')]
    public string $type = 'credit_card';

    #[Validate('nullable|string|max:255')]
    public ?string $lender = null;

    #[Validate('required|numeric|min:0.01')]
    public string $current_balance = '';

    #[Validate('nullable|numeric|min:0.01')]
    public ?string $original_balance = null;

    #[Validate('required|numeric|min:0|max:999.999')]
    public string $apr = '';

    #[Validate('required|numeric|min:0.01')]
    public string $minimum_payment = '';

    #[Validate('nullable|integer|min:1|max:28')]
    public ?int $due_day = null;

    #[Validate('nullable|numeric|min:0.01')]
    public ?string $recovery_fixed_payment = null;

    #[Validate('nullable|integer|min:1|max:120')]
    public ?int $recovery_duration_months = null;

    #[Validate('nullable|date')]
    public ?string $recovery_start_date = null;

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        assert($user !== null);

        $isRecovery = $this->type === 'recovery_plan';

        $recoveryTerms = $isRecovery ? [
            'fixed_payment' => (int) round((float) $this->recovery_fixed_payment * 100),
            'duration_months' => $this->recovery_duration_months,
            'start_date' => $this->recovery_start_date,
        ] : null;

        Debt::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'type' => $this->type,
            'lender' => $this->lender,
            'current_balance' => (int) round((float) $this->current_balance * 100),
            'original_balance' => $this->original_balance ? (int) round((float) $this->original_balance * 100) : null,
            'apr' => (float) $this->apr,
            'minimum_payment' => (int) round((float) $this->minimum_payment * 100),
            'due_day' => $this->due_day,
            'is_in_recovery' => $isRecovery,
            'recovery_terms' => $recoveryTerms,
        ]);

        $this->redirect(route('debt.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.debt.add-manual-debt');
    }
}
