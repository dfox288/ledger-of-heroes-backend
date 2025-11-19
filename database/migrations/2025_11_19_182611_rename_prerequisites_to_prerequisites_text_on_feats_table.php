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
        Schema::table('feats', function (Blueprint $table) {
            $table->renameColumn('prerequisites', 'prerequisites_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feats', function (Blueprint $table) {
            $table->renameColumn('prerequisites_text', 'prerequisites');
        });
    }
};
