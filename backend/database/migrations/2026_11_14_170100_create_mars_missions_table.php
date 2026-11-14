<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mars_missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('origin_region_id');
            $table->string('destination_region_id');
            $table->json('waypoints')->nullable();
            $table->date('departure_date');
            $table->date('arrival_date');
            $table->unsignedSmallInteger('participants');
            $table->string('mission_type');
            $table->text('objective');
            $table->string('risk_tolerance');
            $table->text('notes')->nullable();
            $table->string('status')->default('planned');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->double('distance_km')->default(0);
            $table->unsignedSmallInteger('estimated_days')->default(0);
            $table->json('checklist')->nullable();
            $table->timestamps();

            $table->foreign('origin_region_id')->references('id')->on('mars_regions')->restrictOnDelete();
            $table->foreign('destination_region_id')->references('id')->on('mars_regions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mars_missions');
    }
};
