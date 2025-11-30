<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ClassFeatureSpellsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function class_feature_can_have_spells(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);
        $spell = Spell::factory()->create(['name' => 'Bless']);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $this->assertCount(1, $feature->spells);
        $this->assertEquals('Bless', $feature->spells->first()->name);
    }

    #[Test]
    public function class_feature_spells_include_pivot_data(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);
        $spell = Spell::factory()->create(['name' => 'Lesser Restoration']);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 3,
            'is_cantrip' => false,
        ]);

        $loadedSpell = $feature->spells()->first();
        $this->assertEquals(3, $loadedSpell->pivot->level_requirement);
    }

    #[Test]
    public function cleric_domain_spells_are_always_prepared(): void
    {
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);

        $this->assertTrue($feature->is_always_prepared);
    }

    #[Test]
    public function druid_circle_spells_are_always_prepared(): void
    {
        $druid = CharacterClass::factory()->create(['name' => 'Druid']);
        $arcticCircle = CharacterClass::factory()->create([
            'name' => 'Circle of the Land (Arctic)',
            'parent_class_id' => $druid->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $arcticCircle->id,
            'feature_name' => 'Circle Spells (Circle of the Land)',
        ]);

        $this->assertTrue($feature->is_always_prepared);
    }

    #[Test]
    public function warlock_expanded_spells_are_not_always_prepared(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock']);
        $fiend = CharacterClass::factory()->create([
            'name' => 'The Fiend',
            'parent_class_id' => $warlock->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $fiend->id,
            'feature_name' => 'Expanded Spell List (The Fiend)',
        ]);

        $this->assertFalse($feature->is_always_prepared);
    }

    #[Test]
    public function base_class_features_are_not_always_prepared(): void
    {
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Second Wind',
        ]);

        $this->assertFalse($feature->is_always_prepared);
    }

    #[Test]
    public function paladin_oath_spells_are_always_prepared(): void
    {
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin']);
        $oathOfDevotion = CharacterClass::factory()->create([
            'name' => 'Oath of Devotion',
            'parent_class_id' => $paladin->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $oathOfDevotion->id,
            'feature_name' => 'Oath Spells (Oath of Devotion)',
        ]);

        $this->assertTrue($feature->is_always_prepared);
    }

    #[Test]
    public function feature_without_loaded_class_relationship_is_not_always_prepared(): void
    {
        // Create a feature and explicitly unset the relationship
        // to simulate the edge case where characterClass returns null
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Some Feature',
        ]);

        // Unset the relationship to simulate a null relationship
        // This tests the defensive null check in getIsAlwaysPreparedAttribute
        $feature->setRelation('characterClass', null);

        $this->assertFalse($feature->is_always_prepared);
    }
}
