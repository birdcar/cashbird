<div class="rounded-lg border border-gray-200 bg-white">
    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Recent Insights</h2>
        <a href="{{ route('insights.index') }}" class="text-sm text-gray-600 hover:text-gray-900" wire:navigate aria-label="View all insights">View all</a>
    </div>

    @if($insights->isEmpty())
        <div class="p-6 text-center text-gray-600">No active insights.</div>
    @else
        <div class="divide-y divide-gray-100">
            @foreach($insights as $insight)
                <div wire:key="{{ $insight->id }}" class="px-6 py-3">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-900">{{ $insight->title }}</p>
                        <span class="rounded px-1.5 py-0.5 text-xs {{ $insight->severity->value === 'action_required' ? 'bg-red-100 text-red-700' : ($insight->severity->value === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ str_replace('_', ' ', $insight->severity->value) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">{{ Str::limit($insight->description, 80) }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
