<div class="space-y-6 rounded-xl bg-sand-100/40 p-5">
    <div class="rounded-xl bg-white/80 p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-sand-900">Where your money went — {{ $currentMonth }}</h2>

        @if($topCategories->isEmpty())
            <p class="text-sm text-sand-400">No spending to show yet — connect a bank account to get started.</p>
        @else
            <div class="space-y-3">
                @php $maxAmount = $topCategories->max('total_amount') ?: 1 @endphp
                @foreach($topCategories as $cat)
                    @php $pct = (int) round(($cat['total_amount'] / $maxAmount) * 100) @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-sand-700">{{ $cat['category_name'] ?? 'Uncategorized' }}</span>
                            <span class="text-sand-500">${{ number_format($cat['total_amount'] / 100, 2) }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded-full bg-sand-100"
                             role="progressbar"
                             aria-valuenow="{{ $pct }}"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-label="{{ $cat['category_name'] ?? 'Uncategorized' }}: ${{ number_format($cat['total_amount'] / 100, 2) }}">
                            <div class="h-2 rounded-full bg-amber-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-xl bg-white/80 p-6">
        <h2 class="mb-4 font-display text-lg font-semibold text-sand-900">Month by month</h2>

        @if(collect($monthOverMonth)->sum('total_amount') === 0)
            <p class="text-sm text-sand-400">Not enough history to show trends yet.</p>
        @else
            @php $maxMonth = collect($monthOverMonth)->max('total_amount') ?: 1 @endphp
            <div role="img" aria-label="Monthly spending trend: {{ collect($monthOverMonth)->map(fn($m) => \Carbon\Carbon::parse($m['month'])->format('M') . ' $' . number_format($m['total_amount'] / 100, 2))->join(', ') }}">
                <div class="flex items-end gap-2" style="height: 120px;" aria-hidden="true">
                    @foreach($monthOverMonth as $month)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="w-full rounded-t bg-amber-400"
                                 style="height: {{ $maxMonth > 0 ? ($month['total_amount'] / $maxMonth) * 100 : 0 }}px">
                            </div>
                            <span class="text-xs text-sand-500">{{ \Carbon\Carbon::parse($month['month'])->format('M') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
