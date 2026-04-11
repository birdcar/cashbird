<?php

declare(strict_types=1);

namespace App\Livewire\Sharing;

use App\Enums\SharingStatus;
use App\Models\SharingInvitation;
use App\Models\User;
use App\Services\WorkOS\FGAService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ShareBudgetCategory extends Component
{
    #[Locked]
    public string $categoryId;

    #[Validate('required|email|exists:users,email')]
    public string $email = '';

    #[Validate('required|in:viewer,editor')]
    public string $relation = 'viewer';

    public function mount(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function share(FGAService $fga): void
    {
        $this->validate();

        $user = auth()->user();
        abort_if($user === null, 401);

        $recipient = User::where('email', $this->email)->first();

        if (! $recipient || $recipient->id === $user->id) {
            $this->addError('email', 'Cannot share with yourself or an invalid user.');

            return;
        }

        if (! $recipient->workos_id) {
            $this->addError('email', 'This user has not completed authentication setup.');

            return;
        }

        $existing = SharingInvitation::where('from_user_id', $user->id)
            ->where('to_user_id', $recipient->id)
            ->where('resource_type', 'budget_category')
            ->where('resource_id', $this->categoryId)
            ->where('status', SharingStatus::Active)
            ->exists();

        if ($existing) {
            $this->addError('email', 'Already shared with this user.');

            return;
        }

        DB::transaction(function () use ($user, $recipient, $fga) {
            $fga->createWarrant(
                'budget_category',
                $this->categoryId,
                $this->relation,
                'user',
                $recipient->workos_id,
            );

            SharingInvitation::create([
                'from_user_id' => $user->id,
                'to_user_id' => $recipient->id,
                'resource_type' => 'budget_category',
                'resource_id' => $this->categoryId,
                'relation' => $this->relation,
                'status' => SharingStatus::Active,
            ]);
        });

        $this->reset('email');
        $this->dispatch('category-shared');
    }

    public function render(): View
    {
        return view('livewire.sharing.share-budget-category');
    }
}
