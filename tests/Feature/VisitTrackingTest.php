<?php

use App\Filament\Widgets\VisitStatsWidget;
use App\Models\Visit;
use App\Models\VisitDailyStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const BROWSER = ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0'];

function visitAt(string $ip, $at): void
{
    (new Visit)->forceFill([
        'route_name' => 'render.index',
        'path' => '/',
        'ip_hash' => Visit::hashIp($ip),
        'created_at' => $at,
        'updated_at' => $at,
    ])->save();
}

it('keeps working with a very long URL and stores only the path', function () {
    $this->get('/?utm_source='.str_repeat('x', 300), BROWSER)->assertOk();

    $visit = Visit::sole();
    expect($visit->path)->toBe('/')
        ->and($visit->route_name)->toBe('render.index')
        ->and($visit->ip_hash)->toBe(Visit::hashIp('127.0.0.1'));
});

it('does not store raw IP addresses, full URLs or user agents', function () {
    expect(Schema::hasColumns('visits', ['path', 'ip_hash']))->toBeTrue()
        ->and(Schema::hasColumn('visits', 'ip_address'))->toBeFalse()
        ->and(Schema::hasColumn('visits', 'url'))->toBeFalse()
        ->and(Schema::hasColumn('visits', 'user_agent'))->toBeFalse();
});

it('does not count crawlers', function () {
    $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
    $this->get('/', ['User-Agent' => ''])->assertOk();

    expect(Visit::count())->toBe(0);
});

it('serves the page even when the visit cannot be stored', function () {
    Schema::drop('visits');

    $this->get('/', BROWSER)->assertOk();
});

it('stores daily totals and deletes raw visits after the retention period', function () {
    $this->travelTo(today()->setTime(12, 0));
    visitAt('1.1.1.1', now()->subDays(100));
    visitAt('2.2.2.2', now()->subDays(100));
    visitAt('1.1.1.1', now()->subDays(3));
    visitAt('1.1.1.1', now()->subDays(3));
    visitAt('3.3.3.3', now());

    $this->artisan('visits:aggregate')->assertSuccessful();
    $this->artisan('visits:aggregate')->assertSuccessful();   // re-running changes nothing

    $old = VisitDailyStat::whereDate('date', today()->subDays(100))->sole();
    $recent = VisitDailyStat::whereDate('date', today()->subDays(3))->sole();
    expect(VisitDailyStat::count())->toBe(2)
        ->and([$old->views, $old->unique_visitors])->toBe([2, 2])
        ->and([$recent->views, $recent->unique_visitors])->toBe([2, 1])
        ->and(Visit::count())->toBe(3);
});

it('builds the dashboard numbers from daily totals and today with at most three queries', function () {
    $this->travelTo(today()->setTime(12, 0));
    VisitDailyStat::create(['date' => today()->subDays(30), 'views' => 5, 'unique_visitors' => 5]);
    VisitDailyStat::create(['date' => today()->subDay(), 'views' => 10, 'unique_visitors' => 4]);
    visitAt('9.9.9.9', now()->subDay());          // already counted in yesterday's total
    visitAt('1.1.1.1', now());
    visitAt('1.1.1.1', now());
    visitAt('2.2.2.2', now());

    DB::enableQueryLog();
    $stats = (fn () => $this->getStats())->call(new VisitStatsWidget);
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(3)
        ->and($stats[0]->getValue())->toBe('18')
        ->and($stats[1]->getValue())->toBe('3')
        ->and($stats[2]->getValue())->toBe('2');
});

it('migrates existing visits without losing any', function () {
    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    DB::table('visits')->insert([
        ['route_name' => 'render.page', 'url' => 'https://example.test/render/shuttles?utm='.str_repeat('y', 200), 'ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'created_at' => now(), 'updated_at' => now()],
        ['route_name' => 'render.index', 'url' => 'https://example.test', 'ip_address' => '10.0.0.2', 'user_agent' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('migrate')->assertSuccessful();

    expect(Visit::orderBy('id')->pluck('path')->all())->toBe(['/render/shuttles', '/'])
        ->and(Visit::orderBy('id')->pluck('ip_hash')->all())->toBe([Visit::hashIp('10.0.0.1'), Visit::hashIp('10.0.0.2')]);
});

it('finishes the migration when a previous run was interrupted halfway', function () {
    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    DB::table('visits')->insert([
        ['route_name' => 'render.index', 'url' => 'https://example.test/?a=1', 'ip_address' => '10.0.0.3', 'user_agent' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    // state left by an interrupted run: new columns and a manually added index already exist
    Schema::table('visits', function ($table) {
        $table->string('path')->default('/');
        $table->string('ip_hash', 64)->nullable();
        $table->index('ip_address', 'tmp_visits_ip');
    });

    $this->artisan('migrate')->assertSuccessful();

    expect(Visit::sole()->ip_hash)->toBe(Visit::hashIp('10.0.0.3'))
        ->and(Schema::hasColumn('visits', 'ip_address'))->toBeFalse()
        ->and(Schema::hasTable('visit_daily_stats'))->toBeTrue();
});
