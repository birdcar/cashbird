<div class="space-y-8">
    <div class="flex items-center gap-4">
        <a href="{{ route('reports.index') }}" class="text-sand-400 transition-colors hover:text-sand-700" wire:navigate aria-label="Back to reports">
            <x-phosphor-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="font-display text-fluid-lg font-bold text-sand-900">{{ $report->title }}</h1>
    </div>

    <div class="rounded-xl border border-sand-200 bg-white p-6 lg:p-8">
        <div class="prose max-w-none text-sand-800 prose-headings:font-display prose-headings:text-sand-900 prose-a:text-amber-600">
            {!! Str::markdown($report->content, ['html_input' => 'strip']) !!}
        </div>
    </div>

    <p class="text-sm text-sand-400">Generated {{ $report->created_at->diffForHumans() }}</p>
</div>
