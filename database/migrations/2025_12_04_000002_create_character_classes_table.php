<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subclass_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('order')->default(1);
            $table->unsignedTinyInteger('hit_dice_spent')->default(0);
            $table->timestamps();

            $table->foreign('character_id')
                ->references('id')
                ->on('characters')
                ->onDelete('cascade');

            $table->foreign('class_id')
                ->references('id')
                ->on('classes');

            $table->foreign('subclass_id')
                ->references('id')
                ->on('classes');

            $table->unique(['character_id', 'class_id']);
            $table->index('character_id');
            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_classes');
    }
};
