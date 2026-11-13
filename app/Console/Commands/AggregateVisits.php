<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Models\VisitDailyStat;
use Illuminate\Console\Command;

class AggregateVisits extends Command
{
    protected $signature = 'visits:aggregate {--days=90 : Keep raw visits for this many days}';

    protected $description = 'Store daily visit totals and delete raw visits older than the retention period';

    public function handle(): int
    {
        $today = today();

        // every finished day still present in the raw table; re-running only refreshes the same totals
        $days = Visit::query()
            ->where('created_at', '<', $today)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques')
            ->groupBy('day')
            ->get();

        foreach ($days as $day) {
            VisitDailyStat::updateOrCreate(
                ['date' => $day->day],
                ['views' => $day->views, 'unique_visitors' => $day->uniques],
            );
        }

        $retention = max(1, (int) $this->option('days'));
        $deleted = Visit::query()->where('created_at', '<', $today->copy()->subDays($retention))->delete();

        $this->info("Aggregated {$days->count()} day(s), deleted {$deleted} visit(s) older than {$retention} days.");

        return self::SUCCESS;
    }
}
