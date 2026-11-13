<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use App\Models\VisitDailyStat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class VisitStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        [$daily, $rawByDay] = $this->dailyTotals(7);
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        $totalVisits = $this->totalVisits($rawByDay, today()->subDays(6));
        $todayVisits = $daily[$today]['views'] ?? 0;
        $yesterdayVisits = $daily[$yesterday]['views'] ?? 0;
        $todayUnique = $daily[$today]['uniques'] ?? 0;
        $yesterdayUnique = $daily[$yesterday]['uniques'] ?? 0;

        $last7DaysData = [];
        for ($i = 6; $i >= 0; $i--) {
            $last7DaysData[] = $daily[today()->subDays($i)->toDateString()]['views'] ?? 0;
        }

        $todayChange = $yesterdayVisits > 0
            ? round((($todayVisits - $yesterdayVisits) / $yesterdayVisits) * 100, 1)
            : ($todayVisits > 0 ? 100 : 0);

        $uniqueChange = $yesterdayUnique > 0
            ? round((($todayUnique - $yesterdayUnique) / $yesterdayUnique) * 100, 1)
            : ($todayUnique > 0 ? 100 : 0);

        return [
            Stat::make('Total Views', Number::format($totalVisits))
                ->description('Total number of visits')
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),

            Stat::make('Views Today', Number::format($todayVisits))
                ->description(
                    $todayChange >= 0
                        ? '+'.$todayChange.'% compared to yesterday'
                        : $todayChange.'% compared to yesterday'
                )
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($last7DaysData)
                ->color($todayChange >= 0 ? 'success' : 'danger'),

            Stat::make('Unique Visitors', Number::format($todayUnique))
                ->description(
                    $uniqueChange >= 0
                        ? '+'.$uniqueChange.'% compared to yesterday'
                        : $uniqueChange.'% compared to yesterday'
                )
                ->descriptionIcon($uniqueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($uniqueChange >= 0 ? 'success' : 'warning'),
        ];
    }

    /**
     * Views and unique visitors per day for the last $days days: stored totals for finished days,
     * raw visits for days not aggregated yet (today, or any day the scheduler missed).
     *
     * @return array{0: array<string, array{views: int, uniques: int}>, 1: array<string, int>}
     */
    private function dailyTotals(int $days): array
    {
        $from = today()->subDays($days - 1);
        $totals = [];

        foreach (VisitDailyStat::where('date', '>=', $from->toDateString())->get(['date', 'views', 'unique_visitors']) as $row) {
            $totals[$row->date->toDateString()] = ['views' => $row->views, 'uniques' => $row->unique_visitors];
        }

        $raw = Visit::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques')
            ->groupBy('day')
            ->get();

        $rawByDay = [];
        foreach ($raw as $row) {
            $day = Carbon::parse($row->day)->toDateString();
            $rawByDay[$day] = (int) $row->views;
            $totals[$day] ??= ['views' => (int) $row->views, 'uniques' => (int) $row->uniques];
        }

        return [$totals, $rawByDay];
    }

    /**
     * All-time views: stored daily totals plus raw visits after the last aggregated day.
     */
    private function totalVisits(array $rawByDay, Carbon $rawFrom): int
    {
        $stored = VisitDailyStat::selectRaw('COALESCE(SUM(views), 0) as views, MAX(date) as last_day')->first();
        $since = $stored?->last_day ? Carbon::parse($stored->last_day)->addDay()->startOfDay() : null;

        // usually everything after the last stored day is already in the raw counts of the last days
        if ($since && $since->gte($rawFrom)) {
            $recent = array_sum(array_filter($rawByDay, fn ($day) => $day >= $since->toDateString(), ARRAY_FILTER_USE_KEY));
        } else {
            $recent = Visit::when($since, fn ($q) => $q->where('created_at', '>=', $since))->count();
        }

        return (int) $stored?->views + $recent;
    }
}
