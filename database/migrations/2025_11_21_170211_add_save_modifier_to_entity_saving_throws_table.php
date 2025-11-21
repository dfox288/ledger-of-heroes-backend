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
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->enum('save_modifier', ['advantage', 'disadvantage'])
                ->nullable()
                ->after('is_initial_save')
                ->comment('NULL = standard save requirement; advantage = grants advantage on saves; disadvantage = imposes disadvantage on saves');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->dropColumn('save_modifier');
        });
    }
};
