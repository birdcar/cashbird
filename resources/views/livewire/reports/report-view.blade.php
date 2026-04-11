<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('reports.index') }}" class="text-gray-500 hover:text-gray-700" wire:navigate aria-label="Back to reports">&larr; Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $report->title }}</h1>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="prose max-w-none text-gray-900">
            {!! Str::markdown($report->content) !!}
        </div>
    </div>

    <p class="text-sm text-gray-600">Generated {{ $report->created_at->diffForHumans() }}</p>
</div>
