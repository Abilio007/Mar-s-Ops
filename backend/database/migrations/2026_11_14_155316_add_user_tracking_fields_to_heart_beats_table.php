<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('heart_beats', function (Blueprint $table): void {
            $table->uuid('id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->double('accuracy_m')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->unique('id');
        });

        DB::table('heart_beats')
            ->whereNull('id')
            ->orderBy('uuid')
            ->lazyById(500, 'uuid')
            ->each(function ($heartBeat): void {
                DB::table('heart_beats')
                    ->where('uuid', $heartBeat->uuid)
                    ->update(['id' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('heart_beats', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropUnique(['id']);
            $table->dropColumn(['id', 'user_id', 'accuracy_m']);
        });
    }
};
