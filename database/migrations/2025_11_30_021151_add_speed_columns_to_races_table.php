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
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedSmallInteger('fly_speed')->nullable()->after('speed');
            $table->unsignedSmallInteger('swim_speed')->nullable()->after('fly_speed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn(['fly_speed', 'swim_speed']);
        });
    }
};
