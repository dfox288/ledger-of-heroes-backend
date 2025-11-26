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
        // Laravel convention: alphabetical order, singular (class < optional_feature)
        Schema::create('class_optional_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('optional_feature_id')->constrained()->cascadeOnDelete();
            $table->string('subclass_name')->nullable();  // "Way of the Four Elements", "Battle Master"
            $table->timestamps();

            // Prevent duplicates
            $table->unique(
                ['class_id', 'optional_feature_id', 'subclass_name'],
                'class_opt_feat_subclass_unique'
            );

            // Indexes for lookups
            $table->index('class_id');
            $table->index('optional_feature_id');
            $table->index('subclass_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_optional_feature');
    }
};
