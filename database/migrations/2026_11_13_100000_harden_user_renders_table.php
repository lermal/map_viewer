<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File size for the moderation queue quota, rejection time for the cleanup,
     * and an empty image once the file of a rejected render has been removed.
     */
    public function up(): void
    {
        Schema::table('user_renders', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->unsignedBigInteger('size')->nullable()->after('image');
            $table->timestamp('rejected_at')->nullable()->after('status');
        });

        DB::table('user_renders')->where('status', 'rejected')->update(['rejected_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('user_renders', function (Blueprint $table) {
            $table->dropColumn(['size', 'rejected_at']);
        });
    }
};
