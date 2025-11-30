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
}
