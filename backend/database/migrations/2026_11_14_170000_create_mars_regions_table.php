<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mars_regions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->double('latitude');
            $table->double('longitude');
            $table->string('source_name');
            $table->string('source_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mars_regions');
    }
};
