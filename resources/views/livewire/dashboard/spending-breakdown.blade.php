<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Spending by Category — {{ $currentMonth }}</h2>

        @if($topCategories->isEmpty())
            <p class="text-sm text-gray-500">No spending data yet. Connect an account and sync transactions.</p>
        @else
            <div class="space-y-3">
                @php $maxAmount = $topCategories->max('total_amount') ?: 1 @endphp
                @foreach($topCategories as $cat)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-700">{{ $cat['category_name'] ?? 'Uncategorized' }}</span>
                            <span class="text-gray-500">${{ number_format($cat['total_amount'] / 100, 2) }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                            <div class="h-2 rounded-full bg-gray-800" style="width: {{ ($cat['total_amount'] / $maxAmount) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Monthly Spending Trend</h2>

        @if(collect($monthOverMonth)->sum('total_amount') === 0)
            <p class="text-sm text-gray-500">No spending history yet.</p>
        @else
            <div class="flex items-end gap-2" style="height: 120px;">
                @php $maxMonth = collect($monthOverMonth)->max('total_amount') ?: 1 @endphp
                @foreach($monthOverMonth as $month)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="w-full rounded-t bg-gray-800"
                             style="height: {{ $maxMonth > 0 ? ($month['total_amount'] / $maxMonth) * 100 : 0 }}px">
                        </div>
                        <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($month['month'])->format('M') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
