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
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->string('proficiency_subcategory')->nullable()->after('proficiency_type');
            $table->index('proficiency_subcategory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropIndex(['proficiency_subcategory']);
            $table->dropColumn('proficiency_subcategory');
        });
    }
};
