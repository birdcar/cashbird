<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\Categorization\CategoryResolver;
use Illuminate\View\View;
use Livewire\Component;

class CategoryOverride extends Component
{
    public string $transactionId;
    public string $selectedCategoryId = '';
    public bool $showModal = false;

    public function mount(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function save(CategoryResolver $resolver): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);
        $category = Category::findOrFail($this->selectedCategoryId);
        $user = auth()->user();

        $transaction->update(['category_id' => $category->id]);
        $resolver->saveOverride($transaction, $category, $user->id);

        $this->showModal = false;
        $this->dispatch('category-updated');
    }

    public function render(): View
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('livewire.transactions.category-override', [
            'categories' => $categories,
        ]);
    }
}
