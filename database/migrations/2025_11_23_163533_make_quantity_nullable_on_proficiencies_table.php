<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes quantity nullable to support choice group pattern.
     *
     * With choice_group/choice_option, only the first item in a group needs quantity.
     * Example: "Choose 2 skills" → First skill has quantity=2, others have null.
     */
    public function up(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->integer('quantity')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
        });
    }
};
