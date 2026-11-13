<?php

use App\Models\Visit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visits keep only what the statistics need: route, path without query string and a keyed hash of the IP.
     * Daily totals are kept separately so raw visits can be removed after the retention period.
     */
    public function up(): void
    {
        // Every step checks the current state, so an interrupted run can simply be started again.
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'path')) {
                $table->string('path')->default('/')->after('route_name');
            }
            if (! Schema::hasColumn('visits', 'ip_hash')) {
                $table->string('ip_hash', 64)->nullable()->after('path');
            }
        });

        if (Schema::hasColumn('visits', 'ip_address')) {
            // temporary indexes: each update below becomes an index lookup instead of a full table scan
            $indexes = collect(Schema::getIndexes('visits'))->pluck('name');
            Schema::table('visits', function (Blueprint $table) use ($indexes) {
                if (! $indexes->contains('tmp_visits_ip')) {
                    $table->index('ip_address', 'tmp_visits_ip');
                }
                if (! $indexes->contains('tmp_visits_url')) {
                    $table->index('url', 'tmp_visits_url');
                }
            });

            // one update per distinct address / URL instead of one per visit
            foreach (DB::table('visits')->whereNull('ip_hash')->select('ip_address')->distinct()->cursor() as $row) {
                DB::table('visits')->where('ip_address', $row->ip_address)
                    ->update(['ip_hash' => Visit::hashIp((string) $row->ip_address)]);
            }
            foreach (DB::table('visits')->select('url')->distinct()->cursor() as $row) {
                DB::table('visits')->where('url', $row->url)
                    ->update(['path' => Visit::pathFromUrl((string) $row->url)]);
            }

            Schema::table('visits', function (Blueprint $table) {
                $table->dropIndex('tmp_visits_ip');
                $table->dropIndex('tmp_visits_url');
                $table->dropColumn(['url', 'ip_address', 'user_agent']);
            });
        }

        if (! Schema::hasTable('visit_daily_stats')) {
            Schema::create('visit_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->unsignedInteger('views');
                $table->unsignedInteger('unique_visitors');
                $table->timestamps();
            });
        }
    }

    /**
     * Raw IP addresses and full URLs cannot be restored; the columns come back empty.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_daily_stats');

        Schema::table('visits', function (Blueprint $table) {
            $table->string('url')->default('');
            $table->string('ip_address', 45)->default('');
            $table->text('user_agent')->nullable();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['path', 'ip_hash']);
        });
    }
};
