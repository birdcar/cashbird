<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Reports</h1>

    @if($reports->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="text-gray-600">No reports generated yet. Reports are generated automatically on the 1st of each month.</p>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="divide-y divide-gray-100">
                @foreach($reports as $report)
                    <a wire:key="{{ $report->id }}" href="{{ route('reports.show', $report) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50" wire:navigate>
                        <div>
                            <p class="font-medium text-gray-900">{{ $report->title }}</p>
                            <p class="text-sm text-gray-600">{{ $report->summary ? Str::limit($report->summary, 100) : 'View full report' }}</p>
                        </div>
                        <span class="text-sm text-gray-600">{{ $report->period_month->format('M Y') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
