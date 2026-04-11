<div class="flex h-[calc(100vh-8rem)] flex-col">
    <h1 class="mb-4 text-2xl font-bold text-gray-900">Ask Cashbird</h1>

    <div class="flex-1 space-y-4 overflow-y-auto rounded-lg border border-gray-200 bg-white p-6" role="log" aria-live="polite" aria-label="Chat messages">
        @if(empty($messages))
            <div class="flex h-full items-center justify-center text-gray-600">
                <p>Ask any question about your finances. Try "How much did I spend on dining this month?"</p>
            </div>
        @else
            @foreach($messages as $i => $message)
                <div wire:key="msg-{{ $i }}" class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-lg px-4 py-3 {{ $message['role'] === 'user' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-900' }}">
                        <span class="sr-only">{{ $message['role'] === 'user' ? 'You' : 'Cashbird' }}:</span>
                        @if($message['role'] === 'assistant')
                            <div class="prose prose-sm max-w-none">
                                {!! Str::markdown($message['content']) !!}
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
        <input wire:model="question" type="text" id="chat-question" placeholder="Ask about your finances..." class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-gray-900 px-6 py-2.5 text-sm font-medium text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50">
            <span wire:loading.remove>Ask</span>
            <span wire:loading>Thinking...</span>
        </button>
    </form>
</div>
