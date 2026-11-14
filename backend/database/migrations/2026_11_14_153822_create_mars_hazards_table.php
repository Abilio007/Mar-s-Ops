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
        Schema::create('mars_hazards', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('source_name');
            $table->string('category');
            $table->string('title');
            $table->text('description')->nullable();
            $table->double('latitude');
            $table->double('longitude');
            $table->double('radius_km')->default(0);
            $table->text('source_url');
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['source_name', 'external_id']);
            $table->index(['category', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mars_hazards');
    }
};
