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
        Schema::create('character_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();

            // Polymorphic source (trait, class_feature, feat, etc.)
            $table->string('feature_type', 50);
            $table->unsignedBigInteger('feature_id');
            $table->enum('source', ['race', 'class', 'background', 'feat', 'item'])->default('class');
            $table->unsignedTinyInteger('level_acquired')->default(1);

            // Usage tracking (for limited-use features)
            $table->unsignedTinyInteger('uses_remaining')->nullable();
            $table->unsignedTinyInteger('max_uses')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('character_id');
            $table->index(['feature_type', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_features');
    }
};
