<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Categorization\CategoryResolver;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResolverTest extends TestCase
{
    use RefreshDatabase;

    private CategoryResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->resolver = new CategoryResolver;
    }

    public function test_resolves_two_level_category_path(): void
    {
        $category = $this->resolver->resolve('Food & Drink > Groceries');

        $this->assertNotNull($category);
        $this->assertEquals('Groceries', $category->name);
        $this->assertNotNull($category->parent_id);
    }

    public function test_resolves_top_level_category(): void
    {
        $category = $this->resolver->resolve('Uncategorized');

        $this->assertNotNull($category);
        $this->assertEquals('Uncategorized', $category->name);
        $this->assertNull($category->parent_id);
    }

    public function test_returns_null_for_unknown_path(): void
    {
        $category = $this->resolver->resolve('Nonexistent > Category');

        $this->assertNull($category);
    }

    public function test_returns_null_for_unknown_child(): void
    {
        $category = $this->resolver->resolve('Food & Drink > Tacos');

        $this->assertNull($category);
    }

    public function test_saves_and_retrieves_override(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'merchant_name' => 'COSTCO',
        ]);

        $this->resolver->saveOverride($transaction, $groceries, $user->id);

        $override = $this->resolver->getOverridesForMerchant('COSTCO', $user->id);
        $this->assertNotNull($override);
        $this->assertEquals($groceries->id, $override->id);
    }

    public function test_override_upserts_on_same_merchant(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();
        $restaurants = Category::where('name', 'Restaurants')->first();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'merchant_name' => 'COSTCO',
        ]);

        $this->resolver->saveOverride($transaction, $groceries, $user->id);
        $this->resolver->saveOverride($transaction, $restaurants, $user->id);

        $this->assertDatabaseCount('category_overrides', 1);
        $override = $this->resolver->getOverridesForMerchant('COSTCO', $user->id);
        $this->assertEquals($restaurants->id, $override->id);
    }

    public function test_overrides_map_returns_full_paths(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();

        CategoryOverride::create([
            'user_id' => $user->id,
            'merchant_name' => 'WALMART',
            'category_id' => $groceries->id,
        ]);

        $map = $this->resolver->getOverridesMap($user->id);

        $this->assertArrayHasKey('WALMART', $map->toArray());
        $this->assertEquals('Food & Drink > Groceries', $map['WALMART']);
    }

    public function test_merchant_cache_populates_after_three_consistent(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();

        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'merchant_name' => 'TRADER JOES',
                'category_id' => $groceries->id,
            ]);
        }

        $cache = $this->resolver->getMerchantCache($user->id);

        $this->assertArrayHasKey('TRADER JOES', $cache->toArray());
        $this->assertEquals($groceries->id, $cache['TRADER JOES']);
    }

    public function test_merchant_cache_does_not_populate_under_three(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();

        for ($i = 0; $i < 2; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'merchant_name' => 'TRADER JOES',
                'category_id' => $groceries->id,
            ]);
        }

        $cache = $this->resolver->getMerchantCache($user->id);

        $this->assertEmpty($cache);
    }

    public function test_skip_override_when_no_merchant_name(): void
    {
        $user = User::factory()->create();
        $groceries = Category::where('name', 'Groceries')->first();
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'merchant_name' => null,
        ]);

        $this->resolver->saveOverride($transaction, $groceries, $user->id);

        $this->assertDatabaseCount('category_overrides', 0);
    }
}
