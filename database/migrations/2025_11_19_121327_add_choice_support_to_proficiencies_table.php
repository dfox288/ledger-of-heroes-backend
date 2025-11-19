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
            $table->boolean('is_choice')->default(false)->after('grants');
            $table->integer('quantity')->default(1)->after('is_choice');
            $table->index('is_choice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropIndex(['is_choice']);
            $table->dropColumn(['is_choice', 'quantity']);
        });
    }
};
