<div class="space-y-8">
    <h1 class="font-display text-fluid-lg font-bold text-sand-900">Insights</h1>

    @if($insights->isEmpty())
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-lightbulb class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="text-sand-600">No insights right now. Check back after your next weekly analysis.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($insights as $insight)
                <div wire:key="{{ $insight->id }}" class="rounded-xl border {{ $insight->severity->value === 'action_required' ? 'border-terracotta-200 bg-terracotta-50' : ($insight->severity->value === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white') }} p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-medium text-sand-900">{{ $insight->title }}</h3>
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $insight->severity->value === 'action_required' ? 'bg-terracotta-100 text-terracotta-700' : ($insight->severity->value === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sand-100 text-sand-600') }}">
                                    {{ str_replace('_', ' ', $insight->severity->value) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-sand-600">{{ $insight->description }}</p>
                            <p class="mt-2 text-xs text-sand-400">{{ ucfirst(str_replace('_', ' ', $insight->type->value)) }} &middot; {{ $insight->created_at->diffForHumans() }}</p>
                        </div>
                        <button wire:click="dismiss('{{ $insight->id }}')" wire:loading.attr="disabled" wire:target="dismiss('{{ $insight->id }}')" class="shrink-0 rounded-lg px-3 py-2.5 text-sm text-sand-400 transition-colors hover:bg-sand-100 hover:text-sand-700 disabled:opacity-50" aria-label="Dismiss: {{ $insight->title }}">
                            Dismiss
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
