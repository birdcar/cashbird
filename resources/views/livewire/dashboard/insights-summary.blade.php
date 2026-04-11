<div class="rounded-xl border border-sand-200 bg-white">
    <div class="flex items-center justify-between border-b border-sand-100 px-6 py-4">
        <h2 class="font-display text-lg font-semibold text-sand-900">Recent Insights</h2>
        <a href="{{ route('insights.index') }}" class="text-sm text-amber-600 hover:text-amber-700" wire:navigate aria-label="View all insights">View all</a>
    </div>

    @if($insights->isEmpty())
        <div class="p-8 text-center">
            <x-phosphor-lightbulb class="mx-auto mb-2 h-8 w-8 text-sand-300" />
            <p class="text-sand-500">No insights yet — check back after some spending.</p>
        </div>
    @else
        <div class="divide-y divide-sand-100">
            @foreach($insights as $insight)
                <div wire:key="{{ $insight->id }}" class="px-6 py-3">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-sand-900">{{ $insight->title }}</p>
                        <span class="rounded px-1.5 py-0.5 text-xs {{ $insight->severity->value === 'action_required' ? 'bg-terracotta-100 text-terracotta-700' : ($insight->severity->value === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sand-100 text-sand-600') }}">
                            {{ str_replace('_', ' ', $insight->severity->value) }}
                        </span>
                    </div>
                    <p class="text-sm text-sand-500">{{ Str::limit($insight->description, 80) }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
