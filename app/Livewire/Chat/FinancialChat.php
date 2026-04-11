<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Ai\Agents\QueryAgent;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FinancialChat extends Component
{
    #[Validate('required|string|max:500')]
    public string $question = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public function ask(): void
    {
        $this->validate();

        $user = auth()->user();
        abort_if($user === null, 401);

        $this->messages[] = ['role' => 'user', 'content' => $this->question];

        $agent = QueryAgent::make($user->id);
        $response = $agent->prompt($this->question);

        $this->messages[] = ['role' => 'assistant', 'content' => (string) $response];
        $this->question = '';

        if (count($this->messages) > 50) {
            $this->messages = array_slice($this->messages, -50);
        }
    }

    public function render(): View
    {
        return view('livewire.chat.financial-chat');
    }
}
