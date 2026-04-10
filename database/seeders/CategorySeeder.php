<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Income' => ['Salary', 'Freelance', 'Interest', 'Dividends', 'Refunds', 'Other Income'],
            'Housing' => ['Rent/Mortgage', 'Utilities', 'Insurance', 'Maintenance', 'Property Tax'],
            'Transportation' => ['Gas', 'Public Transit', 'Ride Share', 'Parking', 'Car Payment', 'Car Insurance'],
            'Food & Drink' => ['Groceries', 'Restaurants', 'Fast Food', 'Coffee', 'Delivery', 'Alcohol'],
            'Shopping' => ['Amazon', 'Clothing', 'Electronics', 'Home Goods', 'Gifts'],
            'Entertainment' => ['Streaming', 'Gaming', 'Events', 'Hobbies'],
            'Health' => ['Insurance Premium', 'Doctor', 'Pharmacy', 'Fitness'],
            'Personal' => ['Haircut', 'Subscriptions', 'Phone', 'Internet'],
            'Debt Payments' => ['Credit Card', 'Payday Loan', 'Student Loan', 'Personal Loan'],
            'Savings & Investments' => ['Transfer to Savings', 'Investment Contribution', '401k'],
            'Fees & Charges' => ['Bank Fee', 'ATM Fee', 'Overdraft', 'Late Fee', 'Interest Charge'],
            'Transfers' => ['Internal Transfer', 'Peer Payment'],
            'Uncategorized' => [],
        ];

        foreach ($categories as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['name' => $parentName, 'parent_id' => null],
                ['is_system' => true],
            );

            foreach ($children as $childName) {
                Category::firstOrCreate(
                    ['name' => $childName, 'parent_id' => $parent->id],
                    ['is_system' => true],
                );
            }
        }
    }
}
