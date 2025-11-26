<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class CharacterClassSubclassLevelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function base_class_returns_subclass_level_from_subclass_features()
    {
        // Fighter gets subclass at level 3
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        // Create a subclass
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'fighter-champion',
            'parent_class_id' => $fighter->id,
            'hit_die' => 10,
        ]);

        // Champion's first feature is at level 3
        ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'level' => 3,
            'feature_name' => 'Improved Critical',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'level' => 7,
            'feature_name' => 'Remarkable Athlete',
        ]);

        $this->assertEquals(3, $fighter->subclass_level);
    }

    #[Test]
    public function base_class_returns_level_1_for_cleric_style_subclasses()
    {
        // Cleric gets subclass at level 1
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'hit_die' => 8,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'cleric-life-domain',
            'parent_class_id' => $cleric->id,
            'hit_die' => 8,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'level' => 1,
            'feature_name' => 'Bonus Proficiency',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'level' => 1,
            'feature_name' => 'Disciple of Life',
        ]);

        $this->assertEquals(1, $cleric->subclass_level);
    }

    #[Test]
    public function base_class_returns_level_2_for_wizard_style_subclasses()
    {
        // Wizard gets subclass at level 2
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
        ]);

        $abjuration = CharacterClass::factory()->create([
            'name' => 'School of Abjuration',
            'slug' => 'wizard-school-of-abjuration',
            'parent_class_id' => $wizard->id,
            'hit_die' => 6,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $abjuration->id,
            'level' => 2,
            'feature_name' => 'Abjuration Savant',
        ]);

        $this->assertEquals(2, $wizard->subclass_level);
    }

    #[Test]
    public function base_class_with_multiple_subclasses_returns_minimum_level()
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        // Create two subclasses, both starting at level 3
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'fighter-champion',
            'parent_class_id' => $fighter->id,
        ]);

        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-battle-master',
            'parent_class_id' => $fighter->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'level' => 3,
            'feature_name' => 'Improved Critical',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $battleMaster->id,
            'level' => 3,
            'feature_name' => 'Combat Superiority',
        ]);

        $this->assertEquals(3, $fighter->subclass_level);
    }

    #[Test]
    public function base_class_without_subclasses_returns_null()
    {
        $classWithoutSubclasses = CharacterClass::factory()->create([
            'name' => 'Commoner',
            'slug' => 'commoner',
            'hit_die' => 4,
        ]);

        $this->assertNull($classWithoutSubclasses->subclass_level);
    }

    #[Test]
    public function subclass_returns_null_for_subclass_level()
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'fighter-champion',
            'parent_class_id' => $fighter->id,
        ]);

        // subclass_level only makes sense for base classes
        $this->assertNull($champion->subclass_level);
    }
}
