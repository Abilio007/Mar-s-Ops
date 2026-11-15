<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earth_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->double('start_latitude');
            $table->double('start_longitude');
            $table->double('destination_latitude');
            $table->double('destination_longitude');
            $table->json('route_geometry');
            $table->json('route_steps');
            $table->unsignedBigInteger('distance_m');
            $table->unsignedBigInteger('duration_s');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('status', 16)->default('planned');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earth_trips');
    }
};
