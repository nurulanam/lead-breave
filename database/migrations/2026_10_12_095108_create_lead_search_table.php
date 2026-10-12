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
        Schema::create('lead_search', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('was_new')->default(false);
            $table->timestamps();

            $table->primary(['lead_id', 'search_id']);
            $table->index('search_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_search');
    }
};
