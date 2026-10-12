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
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('industry');
            $table->string('location');
            $table->string('zip', 20)->nullable();
            $table->char('country', 2)->default('US');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->default(5000);
            $table->string('tiling', 20)->default('grid');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('tiles_total')->default(0);
            $table->unsignedInteger('tiles_done')->default(0);
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('leads_created')->default(0);
            $table->unsignedInteger('leads_linked')->default(0);
            $table->unsignedInteger('api_cost_units')->default(0);
            $table->string('stop_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('searches');
    }
};
