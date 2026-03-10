<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoUpdateRestaurantStatus extends Command
{
    protected $signature   = 'restaurants:update-status';
    protected $description = 'Auto open/close restaurants based on their schedule';

    public function handle(): void
    {
        $now = Carbon::now()->format('H:i:s');

        // Only touch restaurants that are NOT manually closed
        Restaurant::where('is_manually_closed', false)
            ->whereNotNull('open_time')
            ->whereNotNull('close_time')
            ->each(function (Restaurant $restaurant) use ($now) {
                $shouldBeOpen = $now >= $restaurant->open_time
                             && $now <  $restaurant->close_time;

                if ($restaurant->is_open !== $shouldBeOpen) {
                    $restaurant->update(['is_open' => $shouldBeOpen]);
                }
            });

        $this->info("Done at {$now}");
    }
}
