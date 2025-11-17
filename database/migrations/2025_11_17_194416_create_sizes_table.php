<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique(); // T, S, M, L, H, G
            $table->string('name', 20);
            $table->timestamps();
        });

        DB::table('sizes')->insert([
            ['code' => 'T', 'name' => 'Tiny'],
            ['code' => 'S', 'name' => 'Small'],
            ['code' => 'M', 'name' => 'Medium'],
            ['code' => 'L', 'name' => 'Large'],
            ['code' => 'H', 'name' => 'Huge'],
            ['code' => 'G', 'name' => 'Gargantuan'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};
