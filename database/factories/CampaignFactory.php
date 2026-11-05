<?php

namespace Database\Factories;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-60 days', '+15 days');
        $endsAt = (clone $startsAt)->modify('+'.fake()->numberBetween(20, 90).' days');

        return [
            'advertiser_id' => Advertiser::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'status' => fake()->randomElement([
                CampaignStatus::Active,
                CampaignStatus::Active,
                CampaignStatus::Scheduled,
                CampaignStatus::Paused,
                CampaignStatus::Draft,
                CampaignStatus::Completed,
            ])->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'daily_start_time' => '07:00:00',
            'daily_end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6],
            'priority' => fake()->numberBetween(1, 10),
            'playback_goal' => fake()->numberBetween(100_000, 2_000_000),
            'budget' => fake()->numberBetween(2_000_000, 40_000_000),
            'target_screen_count' => 0,
        ];
    }
}
