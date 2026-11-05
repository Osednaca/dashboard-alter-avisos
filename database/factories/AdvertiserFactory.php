<?php

namespace Database\Factories;

use App\Domain\Advertisers\Enums\AdvertiserStatus;
use App\Domain\Advertisers\Models\Advertiser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Advertiser>
 */
class AdvertiserFactory extends Factory
{
    protected $model = Advertiser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'status' => fake()->randomElement([
                AdvertiserStatus::Active,
                AdvertiserStatus::Active,
                AdvertiserStatus::Prospect,
                AdvertiserStatus::Inactive,
            ])->value,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => '+57 '.fake()->numerify('3## ### ####'),
            'billing_name' => $name.' S.A.S.',
            'billing_tax_id' => fake()->numerify('###').'.'.fake()->numerify('###').'.'.fake()->numerify('###').'-'.fake()->numberBetween(1, 9),
            'billing_email' => 'pagos@'.Str::slug($name).'.co',
            'billing_address' => fake()->address(),
        ];
    }
}
