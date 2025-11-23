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
        Schema::create('class_feature_special_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_feature_id');
            $table->string('tag', 255);

            $table->foreign('class_feature_id')
                ->references('id')
                ->on('class_features')
                ->onDelete('cascade');

            $table->index('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_feature_special_tags');
    }
};
