<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heart_beats', function (Blueprint $table): void {
            $table->string('coordinate_system', 10)->default('mars')->after('accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('heart_beats', function (Blueprint $table): void {
            $table->dropColumn('coordinate_system');
        });
    }
};
