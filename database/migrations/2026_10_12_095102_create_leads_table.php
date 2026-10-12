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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->nullable()->unique();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('phone_e164', 20)->nullable()->index();
            $table->string('website', 2048)->nullable();
            $table->string('domain')->nullable()->index();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('category')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->string('status', 30)->default('new');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('places');
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_action_at']);
            $table->index(['owner_id', 'status']);
            $table->index('fit_score');

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['name', 'city']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
