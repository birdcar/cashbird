<div class="flex h-[calc(100vh-8rem)] flex-col">
    <h1 class="mb-4 font-display text-fluid-lg font-bold text-sand-900">Ask Cashbird</h1>

    <div class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-sand-200 bg-white p-6" role="log" aria-live="polite" aria-label="Chat messages">
        @if(empty($messages))
            <div class="flex h-full flex-col items-center justify-center text-center">
                <x-phosphor-chat-circle-text class="mb-3 h-10 w-10 text-sand-300" />
                <p class="text-sand-600">Ask anything about your finances.</p>
                <p class="mt-1 text-sm text-sand-400">Try "How much did I spend on dining this month?"</p>
            </div>
        @else
            @foreach($messages as $i => $message)
                <div wire:key="msg-{{ $i }}" class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $message['role'] === 'user' ? 'bg-amber-500 text-white' : 'bg-sand-100 text-sand-900' }}">
                        <span class="sr-only">{{ $message['role'] === 'user' ? 'You' : 'Cashbird' }}:</span>
                        @if($message['role'] === 'assistant')
                            <div class="prose prose-sm max-w-none prose-headings:text-sand-900 prose-a:text-amber-600">
                                {!! Str::markdown($message['content'], ['html_input' => 'strip']) !!}
                            </div>
                        @else
                            <p class="text-sm">{{ $message['content'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <form wire:submit="ask" class="mt-4 flex gap-3">
        <label for="chat-question" class="sr-only">Your question</label>
        <input wire:model="question" type="text" id="chat-question" placeholder="Ask about your finances..." class="flex-1 rounded-lg border-sand-300 bg-white text-sand-900 shadow-sm placeholder:text-sand-400 focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-amber-500 px-6 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50">
            <span wire:loading.remove>Ask</span>
            <span wire:loading>Thinking...</span>
        </button>
    </form>
</div>
