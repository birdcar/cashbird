<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Models\Category;
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
        $this->selectedCategoryId = '';
        $this->showModal = true;
    }

    public function save(CategoryResolver $resolver): void
    {
        $user = auth()->user();
        assert($user !== null);

        $transaction = $user->transactions()->findOrFail($this->transactionId);
        $category = Category::findOrFail($this->selectedCategoryId);

        $transaction->update(['category_id' => $category->id]);
        $resolver->saveOverride($transaction, $category, $user->id);

        $this->showModal = false;
        session()->flash('success', 'Category updated.');
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
