<?php

declare(strict_types=1);

namespace App\Livewire\Sharing;

use App\Models\SharingInvitation;
use App\Services\WorkOS\FGAService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageSharing extends Component
{
    /** @return Collection<int, SharingInvitation> */
    #[Computed]
    public function invitations(): Collection
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        return SharingInvitation::where('from_user_id', $user->id)
            ->active()
            ->with('toUser')
            ->orderByDesc('created_at')
            ->get();
    }

    public function revoke(string $invitationId, FGAService $fga): void
    {
        $user = auth()->user();
        abort_if($user === null, 401);

        $invitation = SharingInvitation::where('from_user_id', $user->id)
            ->where('id', $invitationId)
            ->firstOrFail();

        $recipient = $invitation->toUser;

        if ($recipient?->workos_id) {
            $fga->deleteWarrant(
                $invitation->resource_type,
                $invitation->resource_id,
                $invitation->relation->value,
                'user',
                $recipient->workos_id,
            );
        }

        $invitation->revoke();

        unset($this->invitations);
    }

    public function render(): View
    {
        return view('livewire.sharing.manage-sharing', [
            'invitations' => $this->invitations,
        ]);
    }
}
