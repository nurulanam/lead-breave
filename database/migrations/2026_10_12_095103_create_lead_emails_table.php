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
        Schema::create('lead_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('source', 20)->default('crawl');
            $table->string('found_on_url', 2048)->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->boolean('is_role')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('verified_status', 20)->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'email']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_emails');
    }
};
