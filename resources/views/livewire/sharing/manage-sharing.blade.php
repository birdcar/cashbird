<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Manage Sharing</h1>

    @if($invitations->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="text-gray-600">You haven't shared any budget categories yet.</p>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="divide-y divide-gray-100">
                @foreach($invitations as $invitation)
                    <div wire:key="{{ $invitation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $invitation->resource_type === 'budget_category' ? 'Budget Category' : $invitation->resource_type }}</p>
                            <p class="text-sm text-gray-600">
                                Shared with {{ $invitation->toUser?->name ?? 'Unknown' }}
                                &middot; {{ ucfirst($invitation->relation->value) }} access
                                &middot; {{ $invitation->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <button wire:click="revoke('{{ $invitation->id }}')" wire:confirm="Are you sure you want to revoke access?" wire:loading.attr="disabled" wire:target="revoke('{{ $invitation->id }}')" class="rounded-lg px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50" aria-label="Revoke access for {{ $invitation->toUser?->name ?? 'this user' }}">
                            Revoke
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
