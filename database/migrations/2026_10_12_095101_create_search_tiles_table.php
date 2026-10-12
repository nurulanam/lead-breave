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
        Schema::create('search_tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('search_tiles')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('radius_m');
            $table->unsignedTinyInteger('page')->default(1);
            $table->text('next_page_token')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('results')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['search_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_tiles');
    }
};
