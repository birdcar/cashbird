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

    private string $organizationId;

    private int $cacheTtl = 60;

    private int $membershipCacheTtl = 86400;

    public function __construct()
    {
        $this->baseUrl = (string) config('workos.fga.base_url', 'https://api.workos.com');
        $this->apiKey = (string) config('workos.api_key', '');
        $this->organizationId = (string) config('workos.fga.organization_id', '');

        if ($this->apiKey === '' && ! app()->runningUnitTests()) {
            throw new \RuntimeException('WorkOS API key is not configured. Set WORKOS_API_KEY in .env.');
        }
    }

    public function getOrganizationMembershipId(string $workosUserId): ?string
    {
        $cacheKey = "fga:membership:{$workosUserId}";

        return Cache::remember($cacheKey, $this->membershipCacheTtl, function () use ($workosUserId) {
            $response = Http::withToken($this->apiKey)
                ->get("{$this->baseUrl}/user_management/organization_memberships", [
                    'user_id' => $workosUserId,
                    'organization_id' => $this->organizationId,
                ]);

            if ($response->failed()) {
                return null;
            }

            $memberships = $response->json('data', []);

            return ! empty($memberships) ? $memberships[0]['id'] : null;
        });
    }

    public function createResource(string $resourceTypeSlug, string $externalId, string $name): ?string
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/authorization/resources", [
                'resource_type_slug' => $resourceTypeSlug,
                'external_id' => $externalId,
                'name' => $name,
                'organization_id' => $this->organizationId,
            ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json('id');
    }

    public function deleteResource(string $resourceId): void
    {
        Http::withToken($this->apiKey)
            ->delete("{$this->baseUrl}/authorization/resources/{$resourceId}")
            ->throw();
    }

    public function assignRole(string $orgMembershipId, string $roleSlug, string $resourceId): void
    {
        Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/authorization/organization_memberships/{$orgMembershipId}/role_assignments", [
                'role_slug' => $roleSlug,
                'resource_id' => $resourceId,
            ])->throw();

        $this->bumpGeneration($resourceId);
    }

    public function removeRole(string $orgMembershipId, string $roleSlug, string $resourceId): void
    {
        Http::withToken($this->apiKey)
            ->delete("{$this->baseUrl}/authorization/organization_memberships/{$orgMembershipId}/role_assignments", [
                'role_slug' => $roleSlug,
                'resource_id' => $resourceId,
            ])->throw();

        $this->bumpGeneration($resourceId);
    }

    public function check(string $orgMembershipId, string $permissionSlug, string $resourceId): bool
    {
        $gen = $this->getGeneration($resourceId);
        $cacheKey = "fga:check:v{$gen}:{$orgMembershipId}:{$permissionSlug}:{$resourceId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($orgMembershipId, $permissionSlug, $resourceId) {
            $response = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/authorization/organization_memberships/{$orgMembershipId}/check", [
                    'permission_slug' => $permissionSlug,
                    'resource_id' => $resourceId,
                ]);

            if ($response->failed()) {
                return false;
            }

            return $response->json('authorized', false);
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function listRoleAssignments(string $resourceId): Collection
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/authorization/role_assignments", [
                'resource_id' => $resourceId,
            ]);

        if ($response->failed()) {
            return collect();
        }

        return collect($response->json('data', []));
    }

    private function bumpGeneration(string $resourceId): void
    {
        $genKey = "fga:gen:{$resourceId}";

        if (! Cache::has($genKey)) {
            Cache::put($genKey, 1, now()->addDays(7));
        } else {
            Cache::increment($genKey);
        }
    }

    private function getGeneration(string $resourceId): int
    {
        return (int) Cache::get("fga:gen:{$resourceId}", 0);
    }
}
