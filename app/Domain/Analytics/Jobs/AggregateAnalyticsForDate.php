<?php

namespace App\Domain\Analytics\Jobs;

use App\Domain\Analytics\Actions\AggregateDailyAnalytics;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateAnalyticsForDate implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $date) {}

    public function handle(AggregateDailyAnalytics $aggregator): void
    {
        $aggregator->handle(Carbon::parse($this->date));
    }

    /**
     * @return array<int, CarbonInterface>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
