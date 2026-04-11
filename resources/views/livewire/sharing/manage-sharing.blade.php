<div class="space-y-8">
    <h1 class="font-display text-fluid-lg font-bold text-sand-900">Sharing</h1>

    @if($invitations->isEmpty())
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-users-three class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="text-sand-600">You haven't shared any budget categories yet.</p>
        </div>
    @else
        <div class="rounded-xl border border-sand-200 bg-white">
            <div class="divide-y divide-sand-100">
                @foreach($invitations as $invitation)
                    <div wire:key="{{ $invitation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-sand-900">{{ $invitation->resource_type === 'budget_category' ? 'Budget Category' : $invitation->resource_type }}</p>
                            <p class="text-sm text-sand-500">
                                Shared with {{ $invitation->toUser?->name ?? 'Unknown' }}
                                &middot; {{ ucfirst($invitation->relation->value) }} access
                                &middot; {{ $invitation->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <button wire:click="revoke('{{ $invitation->id }}')" wire:confirm="Are you sure you want to revoke access?" wire:loading.attr="disabled" wire:target="revoke('{{ $invitation->id }}')" class="rounded-lg px-3 py-2.5 text-sm text-terracotta-600 transition-colors hover:bg-terracotta-50 disabled:opacity-50" aria-label="Revoke access for {{ $invitation->toUser?->name ?? 'this user' }}">
                            Revoke
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
