<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds parent_feature_id to support nesting choice options under parent features.
     * For example, "Fighting Style: Archery" is a child of "Fighting Style".
     *
     * Also populates existing data based on the "{Parent}: {Option}" naming convention.
     */
    public function up(): void
    {
        // Step 1: Add the column
        Schema::table('class_features', function (Blueprint $table) {
            $table->foreignId('parent_feature_id')
                ->nullable()
                ->after('is_multiclass_only')
                ->constrained('class_features')
                ->nullOnDelete();

            $table->index('parent_feature_id');
        });

        // Step 2: Populate parent_feature_id for existing data
        // Features with colon pattern "Parent: Option" get linked to their parent
        $this->populateParentFeatureIds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_features', function (Blueprint $table) {
            $table->dropForeign(['parent_feature_id']);
            $table->dropIndex(['parent_feature_id']);
            $table->dropColumn('parent_feature_id');
        });
    }

    /**
     * Populate parent_feature_id for existing features using naming convention.
     *
     * Matches features like "Fighting Style: Archery" to parent "Fighting Style"
     * within the same class and level.
     */
    private function populateParentFeatureIds(): void
    {
        // Find all features with colon pattern that are optional (likely choice options)
        // and link them to their parent feature
        DB::statement("
            UPDATE class_features AS child
            JOIN class_features AS parent ON (
                parent.class_id = child.class_id
                AND parent.level = child.level
                AND parent.feature_name = SUBSTRING_INDEX(child.feature_name, ':', 1)
                AND parent.id != child.id
            )
            SET child.parent_feature_id = parent.id
            WHERE child.feature_name LIKE '%:%'
            AND child.is_optional = 1
        ");
    }
};
