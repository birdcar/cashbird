<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Shared With Me</h1>

    @if($invitations->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="text-gray-600">No budget categories have been shared with you yet.</p>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="divide-y divide-gray-100">
                @foreach($invitations as $invitation)
                    <div wire:key="{{ $invitation->id }}" class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $invitation->resource_type === 'budget_category' ? 'Budget Category' : $invitation->resource_type }}</p>
                            <p class="text-sm text-gray-600">
                                Shared by {{ $invitation->fromUser?->name ?? 'Unknown' }}
                                &middot; {{ ucfirst($invitation->relation->value) }} access
                                &middot; {{ $invitation->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                            {{ ucfirst($invitation->relation->value) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
