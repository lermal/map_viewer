<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class VisitStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalVisits = Visit::count();
        $todayVisits = Visit::today()->count();
        $yesterdayVisits = Visit::whereDate('created_at', today()->subDay())->count();
        $todayUnique = Visit::getUniqueVisitorsCount(Visit::today());
        $yesterdayUnique = Visit::getUniqueVisitorsCount(Visit::whereDate('created_at', today()->subDay()));

        $last7DaysData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $last7DaysData[] = Visit::whereDate('created_at', $date)->count();
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
}
