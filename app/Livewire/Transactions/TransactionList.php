<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $sortBy = 'date';
    public string $sortDir = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
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
            $query->where('description', 'like', "%{$this->search}%");
        }

        if ($this->dateFrom !== '') {
            $query->where('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->where('date', '<=', $this->dateTo);
        }

        $transactions = $query->orderBy($this->sortBy, $this->sortDir)
            ->paginate(25);

        return view('livewire.transactions.transaction-list', [
            'transactions' => $transactions,
        ]);
    }
}
