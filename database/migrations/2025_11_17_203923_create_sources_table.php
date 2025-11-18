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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 255);
            $table->string('publisher', 100)->default('Wizards of the Coast');
            $table->unsignedSmallInteger('publication_year');
            $table->string('edition', 20);

            // NO timestamps - static compendium data doesn't need created_at/updated_at
        });

        // Data seeding moved to DatabaseSeeder
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
