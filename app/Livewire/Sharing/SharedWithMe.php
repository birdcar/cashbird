<?php

declare(strict_types=1);

namespace App\Livewire\Sharing;

use App\Models\SharingInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SharedWithMe extends Component
{
    /** @return Collection<int, SharingInvitation> */
    #[Computed]
    public function invitations(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return SharingInvitation::where('to_user_id', $user->id)
            ->active()
            ->with('fromUser')
            ->orderByDesc('created_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.sharing.shared-with-me', [
            'invitations' => $this->invitations,
        ]);
    }
}
