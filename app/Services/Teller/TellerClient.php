<?php

declare(strict_types=1);

namespace App\Services\Teller;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class TellerClient
{
    public function __construct(
        private readonly ?string $certPath,
        private readonly ?string $keyPath,
        private readonly string $baseUrl = 'https://api.teller.io',
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function listAccounts(string $accessToken): Collection
    {
        $response = $this->request($accessToken)->get("{$this->baseUrl}/accounts");

        $response->throw();

        return collect($response->json());
    }

    /** @return array<string, mixed> */
    public function getAccount(string $accessToken, string $accountId): array
    {
        $response = $this->request($accessToken)->get("{$this->baseUrl}/accounts/{$accountId}");

        $response->throw();

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function getAccountBalances(string $accessToken, string $accountId): array
    {
        $response = $this->request($accessToken)->get("{$this->baseUrl}/accounts/{$accountId}/balances");

        $response->throw();

        return $response->json();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function listTransactions(string $accessToken, string $accountId, ?string $fromId = null): Collection
    {
        $query = [];
        if ($fromId !== null) {
            $query['from_id'] = $fromId;
        }

        $response = $this->request($accessToken)
            ->get("{$this->baseUrl}/accounts/{$accountId}/transactions", $query);

        $response->throw();

        return collect($response->json());
    }

    /** @return array<string, mixed> */
    public function getIdentity(string $accessToken): array
    {
        $response = $this->request($accessToken)->get("{$this->baseUrl}/identity");

        $response->throw();

        return $response->json();
    }

    private function request(string $accessToken): PendingRequest
    {
        $request = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(5)
            ->retry(3, function (int $attempt) {
                return match ($attempt) {
                    1 => 1000,
                    2 => 5000,
                    default => 30000,
                };
            }, fn ($exception) => $exception->response?->status() === 429);

        if ($this->certPath && $this->keyPath) {
            $request = $request->withOptions([
                'cert' => $this->certPath,
                'ssl_key' => $this->keyPath,
            ]);
        }

        return $request;
    }
}
