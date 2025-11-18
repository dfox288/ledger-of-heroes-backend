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
        // Data seeding moved to DatabaseSeeder
        // This migration file is kept for historical reference but contains no schema changes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema changes to reverse
    }
};
