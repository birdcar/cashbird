<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Models\Category;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    private const SORTABLE_COLUMNS = ['date', 'amount', 'description', 'merchant_name', 'status'];

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $categoryFilter = '';
    public string $sortBy = 'date';
    public string $sortDir = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    public function render(): View
    {
        $query = auth()->user()->transactions()
            ->with(['account', 'category']);

        if ($this->search !== '') {
            $query->where('description', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $this->search) . '%');
        }

        if ($this->dateFrom !== '' && strtotime($this->dateFrom) !== false) {
            $query->where('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '' && strtotime($this->dateTo) !== false) {
            $query->where('date', '<=', $this->dateTo);
        }

        if ($this->categoryFilter !== '') {
            $query->where('category_id', $this->categoryFilter);
        }

        $safeSortBy = in_array($this->sortBy, self::SORTABLE_COLUMNS, true) ? $this->sortBy : 'date';
        $safeSortDir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        $transactions = $query->orderBy($safeSortBy, $safeSortDir)
            ->paginate(25);

        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('livewire.transactions.transaction-list', [
            'transactions' => $transactions,
            'categories' => $categories,
        ]);
    }
}
