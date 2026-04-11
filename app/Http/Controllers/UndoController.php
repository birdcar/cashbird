<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\BudgetProposal;
use App\Models\SharingInvitation;
use App\Services\WorkOS\FGAService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UndoController extends Controller
{
    public function undoProposalApprove(Request $request): RedirectResponse
    {
        $proposalId = $request->session()->pull('undo_proposal_id');
        $undoAt = $request->session()->pull('undo_proposal_at');
        if (! $proposalId || ! $undoAt || now()->diffInSeconds($undoAt) > 30) {
            return redirect()->route('budget.index');
        }

        $user = auth()->user();
        $proposal = BudgetProposal::with('period.budget')
            ->whereHas('period.budget', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->where('id', $proposalId)
            ->first();

        if (! $proposal || $proposal->status !== 'approved') {
            return redirect()->route('budget.index');
        }

        DB::transaction(function () use ($proposal) {
            foreach ($proposal->changes as $change) {
                BudgetAllocation::where('budget_period_id', $proposal->budget_period_id)
                    ->where('category_id', $change['category_id'])
                    ->update(['allocated_amount' => $change['old_amount']]);
            }

            $period = $proposal->period;
            $period->update([
                'total_allocated' => $period->allocations()->sum('allocated_amount'),
            ]);

            $proposal->update([
                'status' => 'pending',
                'reviewed_at' => null,
            ]);
        });

        session()->flash('success', 'Budget changes reverted.');

        return redirect()->route('budget.index');
    }

    public function undoSharingRevoke(Request $request, FGAService $fga): RedirectResponse
    {
        $invitationId = $request->session()->pull('undo_invitation_id');
        $undoAt = $request->session()->pull('undo_invitation_at');
        if (! $invitationId || ! $undoAt || now()->diffInSeconds($undoAt) > 30) {
            return redirect()->route('sharing.index');
        }

        $user = auth()->user();
        $invitation = SharingInvitation::where('from_user_id', $user->id)
            ->where('id', $invitationId)
            ->first();

        if (! $invitation || $invitation->status->value !== 'revoked') {
            return redirect()->route('sharing.index');
        }

        DB::transaction(function () use ($invitation, $fga) {
            $invitation->update(['status' => 'active']);

            $recipient = $invitation->toUser;
            if ($recipient?->workos_id) {
                $membershipId = $fga->getOrganizationMembershipId($recipient->workos_id);
                if ($membershipId) {
                    $fga->assignRole(
                        $membershipId,
                        $invitation->relation->value,
                        $invitation->resource_id,
                    );
                }
            }
        });

        session()->flash('success', 'Access restored.');

        return redirect()->route('sharing.index');
    }
}
