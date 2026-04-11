<div class="space-y-8">
    <h1 class="font-display text-fluid-lg font-bold text-sand-900">Reports</h1>

    @if($reports->isEmpty())
        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center">
            <x-phosphor-file-text class="mx-auto mb-3 h-10 w-10 text-sand-300" />
            <p class="text-sand-600">No reports yet. Your first one will be ready on the 1st of next month.</p>
        </div>
    @else
        <div class="rounded-xl border border-sand-200 bg-white">
            <div class="divide-y divide-sand-100">
                @foreach($reports as $report)
                    <a wire:key="{{ $report->id }}" href="{{ route('reports.show', $report) }}" class="flex items-center justify-between px-6 py-4 transition-colors hover:bg-sand-50" wire:navigate>
                        <div>
                            <p class="font-medium text-sand-900">{{ $report->title }}</p>
                            <p class="text-sm text-sand-500">{{ $report->summary ? Str::limit($report->summary, 100) : 'View full report' }}</p>
                        </div>
                        <span class="text-sm text-sand-500">{{ $report->period_month->format('M Y') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
