<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes legacy single-class columns now that multiclass support
     * uses the character_classes junction table instead.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropIndex(['class_id']);
            $table->dropColumn(['level', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('name');
            $table->foreignId('class_id')->nullable()->after('race_id')->constrained('classes')->nullOnDelete();
            $table->index('class_id');
        });
    }
};
