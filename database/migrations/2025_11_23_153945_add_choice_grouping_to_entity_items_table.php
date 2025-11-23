<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Groups equipment choices together so that related options can be identified.
     *
     * Example: Rogue starting equipment has 3 choice groups:
     * - choice_1: (a) rapier OR (b) shortsword
     * - choice_2: (a) shortbow+arrows OR (b) shortsword
     * - choice_3: (a) burglar's pack OR (b) dungeoneer's pack OR (c) explorer's pack
     */
    public function up(): void
    {
        Schema::table('entity_items', function (Blueprint $table) {
            $table->string('choice_group')->nullable()->after('is_choice')
                ->comment('Groups related choice options together (e.g., "choice_1", "choice_2")');
            $table->integer('choice_option')->nullable()->after('choice_group')
                ->comment('Option number within a choice group (1=a, 2=b, 3=c)');

            $table->index('choice_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_items', function (Blueprint $table) {
            $table->dropIndex(['choice_group']);
            $table->dropColumn(['choice_group', 'choice_option']);
        });
    }
};
