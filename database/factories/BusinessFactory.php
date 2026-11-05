<?php

namespace Database\Factories;

use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'category' => fake()->randomElement(BusinessCategory::cases())->value,
            'status' => fake()->randomElement([
                BusinessStatus::Active,
                BusinessStatus::Active,
                BusinessStatus::Active,
                BusinessStatus::Onboarding,
                BusinessStatus::Suspended,
                BusinessStatus::Inactive,
            ])->value,
            'timezone' => 'America/Bogota',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => '+57 '.fake()->numerify('3## ### ####'),
            'metadata' => ['plan' => fake()->randomElement(['starter', 'growth', 'enterprise'])],
        ];
    }
}
