<?php

declare(strict_types=1);

namespace App\Livewire\Savings;

use App\Enums\GoalStatus;
use App\Models\SavingsGoal;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Add Goal')]
class CreateGoal extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|numeric|min:0.01')]
    public string $target_amount = '';

    #[Validate('nullable|date|after:today')]
    public ?string $target_date = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $monthly_contribution = null;

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        abort_if($user === null, 401);

        $maxPriority = SavingsGoal::where('user_id', $user->id)->max('priority') ?? -1;

        SavingsGoal::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'target_amount' => Money::toCents($this->target_amount),
            'current_balance' => 0,
            'target_date' => $this->target_date,
            'monthly_contribution' => $this->monthly_contribution ? Money::toCents($this->monthly_contribution) : 0,
            'priority' => $maxPriority + 1,
            'status' => GoalStatus::Active,
        ]);

        $this->redirect(route('savings.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.savings.create-goal');
    }
}
