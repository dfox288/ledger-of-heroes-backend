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
            $table->unsignedTinyInteger('level')->nullable()->after('proficiency_name')
                ->comment('Character level when this proficiency is gained (e.g., Bonus Proficiency at level 3)');

            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });
    }
};
