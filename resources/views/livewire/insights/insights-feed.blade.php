<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Insights</h1>

    @if($insights->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="text-gray-600">No active insights. Check back after your next weekly analysis.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($insights as $insight)
                <div wire:key="{{ $insight->id }}" class="rounded-lg border {{ $insight->severity->value === 'action_required' ? 'border-red-200 bg-red-50' : ($insight->severity->value === 'warning' ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200 bg-white') }} p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-medium text-gray-900">{{ $insight->title }}</h3>
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $insight->severity->value === 'action_required' ? 'bg-red-100 text-red-700' : ($insight->severity->value === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ str_replace('_', ' ', $insight->severity->value) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $insight->description }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $insight->type->value)) }} &middot; {{ $insight->created_at->diffForHumans() }}</p>
                        </div>
                        <button wire:click="dismiss('{{ $insight->id }}')" class="shrink-0 rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                            Dismiss
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
