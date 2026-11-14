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
        Schema::create('mars_terrain_cells', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('source_name');
            $table->double('latitude');
            $table->double('longitude');
            $table->double('elevation_m')->nullable();
            $table->double('slope_degrees');
            $table->double('roughness_m')->nullable();
            $table->double('cell_size_km');
            $table->text('source_url');
            $table->timestamps();

            $table->unique(['source_name', 'external_id']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mars_terrain_cells');
    }
};
