<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add nullable slug column
        Schema::table('classes', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        // Step 2: Backfill slugs for existing classes
        // Handle base classes first, then subclasses
        $classes = DB::table('classes')
            ->orderBy('parent_class_id', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($classes as $class) {
            $slug = $this->generateClassSlug($class);

            DB::table('classes')
                ->where('id', $class->id)
                ->update(['slug' => $slug]);
        }

        // Step 3: Make slug NOT NULL and add unique constraint
        Schema::table('classes', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Generate slug for a class, handling parent/subclass relationships.
     */
    private function generateClassSlug(object $class): string
    {
        // If this is a base class (no parent), just slug the name
        if ($class->parent_class_id === null) {
            return Str::slug($class->name);
        }

        // For subclasses, get the parent class name and combine
        $parentClass = DB::table('character_classes')
            ->where('id', $class->parent_class_id)
            ->first();

        if ($parentClass) {
            return Str::slug($parentClass->name).'-'.Str::slug($class->name);
        }

        // Fallback: just slug the subclass name
        return Str::slug($class->name);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
