<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Services\Importers\FeatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class FeatImporterPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    private FeatImporter $importer;

    protected $seed = true; // Seed ability_scores, proficiency_types, races

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new FeatImporter;
    }

    #[Test]
    public function it_imports_feat_with_ability_score_prerequisite()
    {
        $featData = [
            'name' => 'Defensive Duelist',
            'prerequisites' => 'Dexterity 13 or higher',
            'description' => 'When wielding a finesse weapon...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 1 prerequisite
        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertEquals(AbilityScore::class, $prereq->prerequisite_type);
        $this->assertEquals(13, $prereq->minimum_value);
        $this->assertEquals(1, $prereq->group_id);

        // Should be DEX
        $dex = AbilityScore::where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $prereq->prerequisite_id);
    }

    #[Test]
    public function it_imports_feat_with_dual_ability_score_prerequisite()
    {
        $featData = [
            'name' => 'Spell Sniper',
            'prerequisites' => 'Intelligence or Wisdom 13 or higher',
            'description' => 'You have learned...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '170'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 2 prerequisites (INT OR WIS)
        $this->assertCount(2, $feat->prerequisites);

        // Both should be in same group (OR logic)
        foreach ($feat->prerequisites as $prereq) {
            $this->assertEquals(AbilityScore::class, $prereq->prerequisite_type);
            $this->assertEquals(13, $prereq->minimum_value);
            $this->assertEquals(1, $prereq->group_id);
        }
    }

    #[Test]
    public function it_imports_feat_with_race_prerequisite()
    {
        Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);

        $featData = [
            'name' => 'Elven Accuracy',
            'prerequisites' => 'Elf',
            'description' => 'The accuracy of elves...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '74'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertEquals(Race::class, $prereq->prerequisite_type);
        $this->assertNotNull($prereq->prerequisite_id);
    }

    #[Test]
    public function it_imports_feat_with_proficiency_prerequisite()
    {
        $featData = [
            'name' => 'Heavily Armored',
            'prerequisites' => 'Proficiency with medium armor',
            'description' => 'You have trained...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '167'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        // Should be ProficiencyType or free-form
        $this->assertTrue(
            $prereq->prerequisite_type === ProficiencyType::class ||
            $prereq->prerequisite_type === null
        );
    }

    #[Test]
    public function it_imports_feat_with_freeform_prerequisite()
    {
        $featData = [
            'name' => 'War Caster',
            'prerequisites' => 'The ability to cast at least one spell',
            'description' => 'You have practiced...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '170'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertNull($prereq->prerequisite_type);
        $this->assertNull($prereq->prerequisite_id);
        $this->assertEquals('The ability to cast at least one spell', $prereq->description);
    }

    #[Test]
    public function it_imports_feat_without_prerequisites()
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have no prerequisites
        $this->assertCount(0, $feat->prerequisites);
    }

    #[Test]
    public function it_deletes_old_prerequisites_on_reimport()
    {
        // First import with prerequisite
        $featData = [
            'name' => 'Test Feat',
            'prerequisites' => 'Strength 13 or higher',
            'description' => 'Test description',
            'sources' => [
                ['code' => 'PHB', 'pages' => '100'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);
        $this->assertCount(1, $feat->prerequisites);
        $oldPrereqId = $feat->prerequisites->first()->id;

        // Reimport with different prerequisite
        $featData['prerequisites'] = 'Dexterity 13 or higher';
        $feat = $this->importer->import($featData);

        // Should still have 1 prerequisite, but different one
        $feat->refresh();
        $this->assertCount(1, $feat->prerequisites);

        $newPrereq = $feat->prerequisites->first();
        $this->assertNotEquals($oldPrereqId, $newPrereq->id);

        // Should be DEX now
        $dex = AbilityScore::where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $newPrereq->prerequisite_id);

        // Old prerequisite should be deleted
        $this->assertNull(EntityPrerequisite::find($oldPrereqId));
    }

    #[Test]
    public function it_imports_feat_with_or_syntax_race_prerequisite()
    {
        // Create races that exist in our seeder
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);

        $featData = [
            'name' => 'Elven Accuracy (Charisma)',
            'prerequisites' => 'Elf or Half-Elf',
            'description' => 'The accuracy of elves is legendary...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '74'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 2 prerequisites (Elf OR Half-Elf)
        $this->assertCount(2, $feat->prerequisites);

        // Both should be Race type in same group (OR logic)
        foreach ($feat->prerequisites as $prereq) {
            $this->assertEquals(Race::class, $prereq->prerequisite_type);
            $this->assertEquals(1, $prereq->group_id);
        }

        // Should have both Elf and Half-Elf
        $raceIds = $feat->prerequisites->pluck('prerequisite_id')->sort()->values()->toArray();
        $expectedIds = collect([$elf->id, $halfElf->id])->sort()->values()->toArray();
        $this->assertEquals($expectedIds, $raceIds);
    }

    #[Test]
    public function it_imports_feat_with_comma_or_syntax_race_prerequisite()
    {
        // Create races
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $halfOrc = Race::factory()->create(['name' => 'Half-Orc', 'slug' => 'half-orc']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        $featData = [
            'name' => 'Prodigy',
            'prerequisites' => 'Half-Elf, Half-Orc, Human',
            'description' => 'You have a knack for learning new things...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '75'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 3 prerequisites
        $this->assertCount(3, $feat->prerequisites);

        // All should be Race type in same group
        foreach ($feat->prerequisites as $prereq) {
            $this->assertEquals(Race::class, $prereq->prerequisite_type);
            $this->assertEquals(1, $prereq->group_id);
        }
    }

    #[Test]
    public function it_imports_feat_with_parenthetical_subrace_prerequisite()
    {
        // Create parent race and subrace
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $highElf = Race::factory()->create([
            'name' => 'High',
            'slug' => 'high-elf',
            'parent_race_id' => $elf->id,
        ]);

        $featData = [
            'name' => 'Fey Teleportation (Intelligence)',
            'prerequisites' => 'Elf (High)',
            'description' => 'Your study of high elven lore...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '74'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 1 prerequisite pointing to the subrace
        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertEquals(Race::class, $prereq->prerequisite_type);
        $this->assertEquals($highElf->id, $prereq->prerequisite_id);
    }

    #[Test]
    public function it_imports_feat_with_drow_subrace_prerequisite()
    {
        // Drow has alternate name format "Drow / Dark"
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $drow = Race::factory()->create([
            'name' => 'Drow / Dark',
            'slug' => 'drow',
            'parent_race_id' => $elf->id,
        ]);

        $featData = [
            'name' => 'Drow High Magic',
            'prerequisites' => 'Elf (Drow)',
            'description' => 'You learn more of the magic typical of dark elves...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '74'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        // Should have 1 prerequisite pointing to the Drow subrace
        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertEquals(Race::class, $prereq->prerequisite_type);
        $this->assertEquals($drow->id, $prereq->prerequisite_id);
    }

    #[Test]
    public function it_imports_feat_with_wood_elf_subrace_prerequisite()
    {
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $woodElf = Race::factory()->create([
            'name' => 'Wood',
            'slug' => 'wood-elf',
            'parent_race_id' => $elf->id,
        ]);

        $featData = [
            'name' => 'Wood Elf Magic',
            'prerequisites' => 'Elf (Wood)',
            'description' => 'You learn the magic of the primeval woods...',
            'sources' => [
                ['code' => 'XGE', 'pages' => '75'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->prerequisites);

        $prereq = $feat->prerequisites->first();
        $this->assertEquals(Race::class, $prereq->prerequisite_type);
        $this->assertEquals($woodElf->id, $prereq->prerequisite_id);
    }
}
