<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use WorkOS\AuthKit\Events\WebhookReceived;
use WorkOS\AuthKit\Http\Controllers\WebhookController;

#[Signature('workos:poll-events {--interval=15 : Polling interval in seconds} {--once : Run once and exit}')]
#[Description('Poll WorkOS Events API with cursor-based pagination')]
class PollWorkOSEvents extends Command
{
    private const string CURSOR_CACHE_KEY = 'workos:events:cursor';

    public function handle(): int
    {
        /** @var string $apiKey */
        $apiKey = config('workos.api_key');

        if (empty($apiKey)) {
            $this->error('WorkOS API key not configured.');

            return self::FAILURE;
        }

        /** @var int $interval */
        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        $this->info("Polling WorkOS Events API every {$interval}s...");

        do {
            $this->poll($apiKey);

            if (! $once) {
                sleep($interval);
            }
        } while (! $once);

        return self::SUCCESS;
    }

    private function poll(string $apiKey): void
    {
        $cursor = Cache::get(self::CURSOR_CACHE_KEY);

        $query = [
            'limit' => 100,
            'order' => 'asc',
        ];

        if ($cursor !== null) {
            $query['after'] = $cursor;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->timeout(30)->get('https://api.workos.com/events', $query);

            if (! $response->successful()) {
                $this->error("WorkOS API returned {$response->status()}: {$response->body()}");

                return;
            }

            /** @var array{data: array<int, array{id: string, event: string, data: array<string, mixed>}>, list_metadata: array{after: ?string}} $body */
            $body = $response->json();
            $events = $body['data'] ?? [];

            foreach ($events as $event) {
                $this->processEvent($event);
            }

            $nextCursor = $body['list_metadata']['after'] ?? null;
            if ($nextCursor !== null) {
                Cache::forever(self::CURSOR_CACHE_KEY, $nextCursor);
            }

            if (count($events) > 0) {
                $this->line('Processed '.count($events).' event(s)');
            }
        } catch (\Exception $e) {
            $this->error("Poll failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array{id: string, event: string, data: array<string, mixed>}  $event
     */
    private function processEvent(array $event): void
    {
        $eventType = $event['event'];
        $eventData = $event['data'];

        $this->info("Event: {$eventType} ({$event['id']})");

        event(new WebhookReceived($eventType, $eventData));

        $eventClass = WebhookController::EVENT_MAP[$eventType] ?? null;
        if ($eventClass !== null) {
            event(new $eventClass($eventData));
        }
    }
}
