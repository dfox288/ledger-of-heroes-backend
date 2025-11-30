<?php

namespace Tests\Unit\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Importers\Concerns\ImportsSubclassSpells;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class ImportsSubclassSpellsTest extends TestCase
{
    use ImportsSubclassSpells;
    use RefreshDatabase;

    private CharacterClass $cleric;

    private CharacterClass $lifeDomain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $this->lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $this->cleric->id,
        ]);
    }

    #[Test]
    public function imports_domain_spells_for_feature(): void
    {
        // Create spells that will be referenced
        Spell::factory()->create(['name' => 'Bless']);
        Spell::factory()->create(['name' => 'Cure Wounds']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        $this->assertCount(2, EntitySpell::where('reference_type', ClassFeature::class)->get());

        $blessSpell = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Bless'))->first();
        $this->assertNotNull($blessSpell);
        $this->assertEquals(1, $blessSpell->level_requirement);
        $this->assertEquals($feature->id, $blessSpell->reference_id);
    }

    #[Test]
    public function imports_multiple_spell_levels(): void
    {
        Spell::factory()->create(['name' => 'Bless']);
        Spell::factory()->create(['name' => 'Cure Wounds']);
        Spell::factory()->create(['name' => 'Lesser Restoration']);
        Spell::factory()->create(['name' => 'Spiritual Weapon']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        $this->assertCount(4, EntitySpell::where('reference_type', ClassFeature::class)->get());

        // Check level 1 spells
        $blessSpell = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Bless'))->first();
        $this->assertEquals(1, $blessSpell->level_requirement);

        // Check level 3 spells
        $lesserRestoration = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Lesser Restoration'))->first();
        $this->assertEquals(3, $lesserRestoration->level_requirement);
    }

    #[Test]
    public function skips_spells_not_found_in_database(): void
    {
        // Only create one of the spells
        Spell::factory()->create(['name' => 'Bless']);
        // Don't create 'Cure Wounds'

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        // Only Bless should be imported
        $this->assertCount(1, EntitySpell::where('reference_type', ClassFeature::class)->get());
    }

    #[Test]
    public function clears_existing_spells_before_import(): void
    {
        $spell = Spell::factory()->create(['name' => 'Old Spell']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);

        // Create existing spell association
        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 1,
        ]);

        // Now import with new description (no spell table)
        $this->importSubclassSpells($feature, 'No spell table here');

        // Old spell should be cleared
        $this->assertCount(0, EntitySpell::where('reference_type', ClassFeature::class)->get());
    }
}
