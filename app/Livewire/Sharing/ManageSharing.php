<?php

declare(strict_types=1);

namespace App\Livewire\Sharing;

use App\Models\SharingInvitation;
use App\Services\WorkOS\FGAService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sharing')]
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

        DB::transaction(function () use ($invitation, $fga) {
            $invitation->revoke();

            $recipient = $invitation->toUser;
            if ($recipient?->workos_id) {
                $membershipId = $fga->getOrganizationMembershipId($recipient->workos_id);
                if ($membershipId) {
                    $fga->removeRole(
                        $membershipId,
                        $invitation->relation->value,
                        $invitation->resource_id,
                    );
                }
            }
        });

        session()->flash('success', 'Access revoked.');
        session()->flash('undo_route', route('undo.sharing'));
        session()->put('undo_invitation_id', $invitation->id);
        session()->put('undo_invitation_at', now());
        unset($this->invitations);
    }

    public function render(): View
    {
        return view('livewire.sharing.manage-sharing', [
            'invitations' => $this->invitations,
        ]);
    }
}
