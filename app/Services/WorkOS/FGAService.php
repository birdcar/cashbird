<?php

declare(strict_types=1);

namespace App\Services\WorkOS;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FGAService
{
    private string $baseUrl;

    private string $apiKey;

    private int $cacheTtl = 60;

    public function __construct()
    {
        $this->baseUrl = config('workos.fga.base_url', 'https://api.workos.com');
        $this->apiKey = config('workos.api_key', '');
    }

    public function createWarrant(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): void
    {
        Http::withToken($this->apiKey)->post("{$this->baseUrl}/fga/v1/warrants", [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'relation' => $relation,
            'subject' => [
                'resource_type' => $subjectType,
                'resource_id' => $subjectId,
            ],
        ])->throw();

        $this->bumpGeneration($resourceType, $resourceId);
    }

    public function deleteWarrant(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): void
    {
        Http::withToken($this->apiKey)->delete("{$this->baseUrl}/fga/v1/warrants", [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'relation' => $relation,
            'subject' => [
                'resource_type' => $subjectType,
                'resource_id' => $subjectId,
            ],
        ])->throw();

        $this->bumpGeneration($resourceType, $resourceId);
    }

    public function check(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId): bool
    {
        $gen = $this->getGeneration($resourceType, $resourceId);
        $cacheKey = "fga:check:v{$gen}:{$resourceType}:{$resourceId}:{$relation}:{$subjectType}:{$subjectId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($resourceType, $resourceId, $relation, $subjectType, $subjectId) {
            $response = Http::withToken($this->apiKey)->post("{$this->baseUrl}/fga/v1/check", [
                'checks' => [
                    [
                        'resource_type' => $resourceType,
                        'resource_id' => $resourceId,
                        'relation' => $relation,
                        'subject' => [
                            'resource_type' => $subjectType,
                            'resource_id' => $subjectId,
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                return false;
            }

            $results = $response->json('results', []);

            return ! empty($results) && ($results[0]['authorized'] ?? false);
        });
    }

    /** @return Collection<int, array{resource_type: string, resource_id: string, relation: string, subject: array{resource_type: string, resource_id: string}}> */
    public function listWarrants(string $resourceType, string $resourceId): Collection
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/fga/v1/warrants", [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]);

        if ($response->failed()) {
            return collect();
        }

        return collect($response->json('data', []));
    }

    /** @param array<int, array{resource_type: string, resource_id: string, relation: string, subject_type: string, subject_id: string}> $checks */
    public function batchCheck(array $checks): array
    {
        $results = [];

        foreach ($checks as $check) {
            $key = "{$check['resource_type']}:{$check['resource_id']}:{$check['relation']}:{$check['subject_type']}:{$check['subject_id']}";
            $results[$key] = $this->check(
                $check['resource_type'],
                $check['resource_id'],
                $check['relation'],
                $check['subject_type'],
                $check['subject_id'],
            );
        }

        return $results;
    }

    private function bumpGeneration(string $resourceType, string $resourceId): void
    {
        $genKey = "fga:gen:{$resourceType}:{$resourceId}";
        Cache::increment($genKey);
    }

    private function getGeneration(string $resourceType, string $resourceId): int
    {
        return (int) Cache::get("fga:gen:{$resourceType}:{$resourceId}", 0);
    }
}
