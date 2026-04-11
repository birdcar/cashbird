<div class="space-y-8">
    <h1 class="font-display text-fluid-lg font-bold text-sand-900">Shared With Me</h1>

    @if($invitations->isEmpty())
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-users-three class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="text-sand-600">Nothing shared with you yet.</p>
        </div>
    @else
        <div class="rounded-xl border border-sand-200 bg-white">
            <div class="divide-y divide-sand-100">
                @foreach($invitations as $invitation)
                    <div wire:key="{{ $invitation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-sand-900">{{ $invitation->resource_type === 'budget_category' ? 'Budget Category' : $invitation->resource_type }}</p>
                            <p class="text-sm text-sand-500">
                                Shared by {{ $invitation->fromUser?->name ?? 'Unknown' }}
                                &middot; {{ ucfirst($invitation->relation->value) }} access
                                &middot; {{ $invitation->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                            {{ ucfirst($invitation->relation->value) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
