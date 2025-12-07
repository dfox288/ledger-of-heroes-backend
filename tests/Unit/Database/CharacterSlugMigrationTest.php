<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class CharacterSlugMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function characters_table_has_race_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('characters', 'race_slug'),
            'Characters table should have race_slug column'
        );
    }

    #[Test]
    public function characters_table_has_background_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('characters', 'background_slug'),
            'Characters table should have background_slug column'
        );
    }

    #[Test]
    public function characters_table_does_not_have_race_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('characters', 'race_id'),
            'Characters table should not have race_id column'
        );
    }

    #[Test]
    public function characters_table_does_not_have_background_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('characters', 'background_id'),
            'Characters table should not have background_id column'
        );
    }

    #[Test]
    public function character_classes_table_has_class_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_classes', 'class_slug'),
            'Character_classes table should have class_slug column'
        );
    }

    #[Test]
    public function character_classes_table_has_subclass_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_classes', 'subclass_slug'),
            'Character_classes table should have subclass_slug column'
        );
    }

    #[Test]
    public function character_classes_table_does_not_have_class_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_classes', 'class_id'),
            'Character_classes table should not have class_id column'
        );
    }

    #[Test]
    public function character_classes_table_does_not_have_subclass_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_classes', 'subclass_id'),
            'Character_classes table should not have subclass_id column'
        );
    }

    #[Test]
    public function character_spells_table_has_spell_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_spells', 'spell_slug'),
            'Character_spells table should have spell_slug column'
        );
    }

    #[Test]
    public function character_spells_table_does_not_have_spell_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_spells', 'spell_id'),
            'Character_spells table should not have spell_id column'
        );
    }

    #[Test]
    public function character_equipment_table_has_item_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_equipment', 'item_slug'),
            'Character_equipment table should have item_slug column'
        );
    }

    #[Test]
    public function character_equipment_table_does_not_have_item_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_equipment', 'item_id'),
            'Character_equipment table should not have item_id column'
        );
    }

    #[Test]
    public function character_languages_table_has_language_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_languages', 'language_slug'),
            'Character_languages table should have language_slug column'
        );
    }

    #[Test]
    public function character_languages_table_does_not_have_language_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_languages', 'language_id'),
            'Character_languages table should not have language_id column'
        );
    }

    #[Test]
    public function character_proficiencies_table_has_skill_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_proficiencies', 'skill_slug'),
            'Character_proficiencies table should have skill_slug column'
        );
    }

    #[Test]
    public function character_proficiencies_table_has_proficiency_type_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_proficiencies', 'proficiency_type_slug'),
            'Character_proficiencies table should have proficiency_type_slug column'
        );
    }

    #[Test]
    public function character_proficiencies_table_does_not_have_skill_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_proficiencies', 'skill_id'),
            'Character_proficiencies table should not have skill_id column'
        );
    }

    #[Test]
    public function character_proficiencies_table_does_not_have_proficiency_type_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_proficiencies', 'proficiency_type_id'),
            'Character_proficiencies table should not have proficiency_type_id column'
        );
    }

    #[Test]
    public function character_conditions_table_has_condition_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('character_conditions', 'condition_slug'),
            'Character_conditions table should have condition_slug column'
        );
    }

    #[Test]
    public function character_conditions_table_does_not_have_condition_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('character_conditions', 'condition_id'),
            'Character_conditions table should not have condition_id column'
        );
    }

    #[Test]
    public function feature_selections_table_has_optional_feature_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('feature_selections', 'optional_feature_slug'),
            'Feature_selections table should have optional_feature_slug column'
        );
    }

    #[Test]
    public function feature_selections_table_has_class_slug_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('feature_selections', 'class_slug'),
            'Feature_selections table should have class_slug column'
        );
    }

    #[Test]
    public function feature_selections_table_does_not_have_optional_feature_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('feature_selections', 'optional_feature_id'),
            'Feature_selections table should not have optional_feature_id column'
        );
    }

    #[Test]
    public function feature_selections_table_does_not_have_class_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('feature_selections', 'class_id'),
            'Feature_selections table should not have class_id column'
        );
    }

    #[Test]
    public function slug_columns_have_indexes(): void
    {
        $columns = [
            'characters' => ['race_slug', 'background_slug'],
            'character_classes' => ['class_slug'],
            'character_spells' => ['spell_slug'],
            'character_equipment' => ['item_slug'],
            'character_languages' => ['language_slug'],
            'character_proficiencies' => ['skill_slug', 'proficiency_type_slug'],
            'character_conditions' => ['condition_slug'],
            'feature_selections' => ['optional_feature_slug', 'class_slug'],
        ];

        foreach ($columns as $table => $slugColumns) {
            foreach ($slugColumns as $column) {
                $this->assertTrue(
                    $this->tableHasIndexOnColumn($table, $column),
                    "Table {$table} should have an index on {$column}"
                );
            }
        }
    }

    private function tableHasIndexOnColumn(string $table, string $column): bool
    {
        $indexes = DB::select("PRAGMA index_list({$table})");

        foreach ($indexes as $index) {
            $columns = DB::select("PRAGMA index_info({$index->name})");
            foreach ($columns as $col) {
                if ($col->name === $column) {
                    return true;
                }
            }
        }

        return false;
    }
}
