<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('visitable');
            $table->string('route_name');
            $table->string('url');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['visitable_type', 'visitable_id']);
            $table->index('route_name');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
