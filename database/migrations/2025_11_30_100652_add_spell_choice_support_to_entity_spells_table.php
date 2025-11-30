<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, make spell_id nullable for choice rows
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->unsignedBigInteger('spell_id')->nullable()->change();
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            // Choice support
            $table->boolean('is_choice')->default(false)->after('is_cantrip');
            $table->unsignedTinyInteger('choice_count')->nullable()->after('is_choice')
                ->comment('Number of spells player picks from this pool');
            $table->string('choice_group')->nullable()->after('choice_count')
                ->comment('Groups rows representing same choice (e.g., "spell_choice_1")');

            // Constraints for spell choices
            $table->unsignedTinyInteger('max_level')->nullable()->after('choice_group')
                ->comment('0=cantrip, 1-9=max spell level for choice');
            $table->unsignedBigInteger('school_id')->nullable()->after('max_level');
            $table->unsignedBigInteger('class_id')->nullable()->after('school_id');
            $table->boolean('is_ritual_only')->default(false)->after('class_id');

            // Foreign keys
            $table->foreign('school_id')->references('id')->on('spell_schools')->onDelete('set null');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');

            // Indexes
            $table->index('is_choice');
            $table->index('choice_group');
        });
    }

    public function down(): void
    {
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['class_id']);
            $table->dropIndex(['is_choice']);
            $table->dropIndex(['choice_group']);
            $table->dropColumn([
                'is_choice',
                'choice_count',
                'choice_group',
                'max_level',
                'school_id',
                'class_id',
                'is_ritual_only',
            ]);
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            $table->unsignedBigInteger('spell_id')->nullable(false)->change();
        });
    }
};
