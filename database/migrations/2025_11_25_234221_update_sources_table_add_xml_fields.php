<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds new fields from source XML files and removes unused edition field.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Remove unused edition field
            $table->dropColumn('edition');

            // Make publication_year nullable (some sources may not have dates)
            $table->unsignedSmallInteger('publication_year')->nullable()->change();

            // Add new fields from XML
            $table->string('url', 500)->nullable()->after('publication_year');
            $table->string('author', 255)->nullable()->after('url');
            $table->string('artist', 255)->nullable()->after('author');
            $table->string('website', 255)->nullable()->after('artist');
            $table->string('category', 100)->nullable()->after('website');
            $table->text('description')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Re-add edition field
            $table->string('edition', 20)->after('publication_year');

            // Revert publication_year to NOT NULL
            $table->unsignedSmallInteger('publication_year')->nullable(false)->change();

            // Remove new fields
            $table->dropColumn(['url', 'author', 'artist', 'website', 'category', 'description']);
        });
    }
};
