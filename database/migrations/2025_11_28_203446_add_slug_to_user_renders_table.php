<?php

use App\Models\UserRender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_renders', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        UserRender::chunk(100, function ($renders) {
            foreach ($renders as $render) {
                if (empty($render->slug)) {
                    do {
                        $slug = Str::random(32);
                    } while (UserRender::where('slug', $slug)->where('id', '!=', $render->id)->exists());

                    $render->update(['slug' => $slug]);
                }
            }
        });

        Schema::table('user_renders', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_renders', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
