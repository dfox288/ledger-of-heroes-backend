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
        Schema::table('modifiers', function (Blueprint $table) {
            $table->boolean('is_choice')->default(false)->after('value');
            $table->integer('choice_count')->nullable()->after('is_choice');
            $table->string('choice_constraint')->nullable()->after('choice_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn(['is_choice', 'choice_count', 'choice_constraint']);
        });
    }
};
