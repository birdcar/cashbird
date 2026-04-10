<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\CategorizationAgent;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorizationAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_builds_category_tree_from_database(): void
    {
        $tree = CategorizationAgent::buildCategoryTree();

        $this->assertStringContainsString('Food & Drink > Groceries', $tree);
        $this->assertStringContainsString('Entertainment > Streaming', $tree);
        $this->assertStringContainsString('Uncategorized', $tree);
    }

    public function test_categorizes_known_merchant(): void
    {
        CategorizationAgent::fake([
            ['category_path' => 'Food & Drink > Groceries', 'confidence' => 'high'],
        ]);

        $agent = CategorizationAgent::make()
            ->withCategoryTree(CategorizationAgent::buildCategoryTree());

        $response = $agent->prompt("Merchant: WALMART SUPERCENTER\nAmount: -\$52.34\nType: debit");

        $this->assertEquals('Food & Drink > Groceries', $response['category_path']);
        $this->assertEquals('high', $response['confidence']);
    }

    public function test_categorizes_streaming_service(): void
    {
        CategorizationAgent::fake([
            ['category_path' => 'Entertainment > Streaming', 'confidence' => 'high'],
        ]);

        $agent = CategorizationAgent::make()
            ->withCategoryTree(CategorizationAgent::buildCategoryTree());

        $response = $agent->prompt("Merchant: NETFLIX.COM\nAmount: -\$15.99\nType: debit");

        $this->assertEquals('Entertainment > Streaming', $response['category_path']);
    }

    public function test_returns_uncategorized_for_unknown(): void
    {
        CategorizationAgent::fake([
            ['category_path' => 'Uncategorized', 'confidence' => 'low'],
        ]);

        $agent = CategorizationAgent::make()
            ->withCategoryTree(CategorizationAgent::buildCategoryTree());

        $response = $agent->prompt('Categorize: PAYMENT THANK YOU, amount: -$100.00');

        $this->assertEquals('Uncategorized', $response['category_path']);
    }

    public function test_includes_overrides_in_instructions(): void
    {
        CategorizationAgent::fake([
            ['category_path' => 'Food & Drink > Groceries', 'confidence' => 'high'],
        ]);

        $agent = CategorizationAgent::make()
            ->withCategoryTree(CategorizationAgent::buildCategoryTree())
            ->withOverrides(['COSTCO' => 'Food & Drink > Groceries']);

        $instructions = $agent->instructions();

        $this->assertStringContainsString('COSTCO', (string) $instructions);
        $this->assertStringContainsString('Food & Drink > Groceries', (string) $instructions);

        $response = $agent->prompt('Categorize: COSTCO WHOLESALE, amount: -$150.00');
        $this->assertEquals('Food & Drink > Groceries', $response['category_path']);
    }

    public function test_assert_prompted_works(): void
    {
        CategorizationAgent::fake([
            ['category_path' => 'Shopping > Amazon', 'confidence' => 'high'],
        ]);

        $agent = CategorizationAgent::make()
            ->withCategoryTree(CategorizationAgent::buildCategoryTree());

        $agent->prompt('Categorize: AMZN MKTP US, amount: -$29.99');

        CategorizationAgent::assertPrompted(fn ($prompt) => $prompt->contains('AMZN MKTP US'));
    }

    public function test_category_seeder_creates_hierarchy(): void
    {
        $parentCount = Category::whereNull('parent_id')->count();
        $totalCount = Category::count();

        $this->assertEquals(13, $parentCount);
        $this->assertGreaterThan(50, $totalCount);

        $foodDrink = Category::where('name', 'Food & Drink')->whereNull('parent_id')->first();
        $this->assertNotNull($foodDrink);
        $this->assertGreaterThan(0, $foodDrink->children()->count());

        $groceries = Category::where('name', 'Groceries')->where('parent_id', $foodDrink->id)->first();
        $this->assertNotNull($groceries);
        $this->assertEquals('Food & Drink > Groceries', $groceries->fullPath());
    }
}
