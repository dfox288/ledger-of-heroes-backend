<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates optional_features and class_optional_feature pivot table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optional_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 255);
            $table->string('feature_type', 255);
            $table->tinyInteger('level_requirement')->unsigned()->nullable();
            $table->string('prerequisite_text', 255)->nullable();
            $table->text('description');
            $table->string('casting_time', 255)->nullable();
            $table->string('range', 255)->nullable();
            $table->string('duration', 255)->nullable();
            $table->foreignId('spell_school_id')->nullable()
                ->constrained('spell_schools')->nullOnDelete();
            $table->string('resource_type', 255)->nullable();
            $table->tinyInteger('resource_cost')->unsigned()->nullable();

            $table->index('feature_type');
            $table->index('level_requirement');
            $table->index('resource_type');
        });

        Schema::create('class_optional_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('optional_feature_id')->constrained()->cascadeOnDelete();
            $table->string('subclass_name', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['class_id', 'optional_feature_id', 'subclass_name'],
                'class_opt_feat_subclass_unique'
            );
            $table->index('subclass_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_optional_feature');
        Schema::dropIfExists('optional_features');
    }
};
