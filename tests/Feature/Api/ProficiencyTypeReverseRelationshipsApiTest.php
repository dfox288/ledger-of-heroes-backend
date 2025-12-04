<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Proficiency;
use App\Models\ProficiencyType;
use App\Models\Race;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ProficiencyTypeReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    // ========================================
    // Classes Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_classes_for_proficiency_type(): void
    {
        $longsword = ProficiencyType::factory()->create(['name' => 'Longsword', 'category' => 'weapon', 'subcategory' => 'martial']);

        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']);

        Proficiency::factory()->create(['reference_type' => CharacterClass::class, 'reference_id' => $fighter->id, 'proficiency_type_id' => $longsword->id]);
        Proficiency::factory()->create(['reference_type' => CharacterClass::class, 'reference_id' => $paladin->id, 'proficiency_type_id' => $longsword->id]);

        $response = $this->getJson("/api/v1/lookups/proficiency-types/{$longsword->id}/classes");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Fighter'])
            ->assertJsonFragment(['name' => 'Paladin'])
            ->assertJsonMissing(['name' => 'Wizard']);
    }

    #[Test]
    public function it_returns_empty_when_proficiency_type_has_no_classes(): void
    {
        $thiefTools = ProficiencyType::factory()->create(['name' => "Thieves' Tools", 'category' => 'tool']);

        $this->assertReturnsEmpty("/api/v1/lookups/proficiency-types/{$thiefTools->id}/classes");
    }

    #[Test]
    public function it_accepts_name_for_classes_endpoint(): void
    {
        $stealth = ProficiencyType::factory()->create(['name' => 'Stealth', 'slug' => 'stealth', 'category' => 'skill']);
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue']);
        Proficiency::factory()->create(['reference_type' => CharacterClass::class, 'reference_id' => $rogue->id, 'proficiency_type_id' => $stealth->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/Stealth/classes');

        $response->assertOk()->assertJsonFragment(['name' => 'Rogue']);
    }

    #[Test]
    public function it_accepts_slug_for_classes_endpoint(): void
    {
        $testWeapon = ProficiencyType::factory()->create(['name' => 'Combat Axe', 'slug' => 'combat-axe', 'category' => 'weapon']);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::factory()->create(['reference_type' => CharacterClass::class, 'reference_id' => $fighter->id, 'proficiency_type_id' => $testWeapon->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/Combat Axe/classes');

        $response->assertOk()->assertJsonFragment(['name' => 'Fighter']);
    }

    #[Test]
    public function it_paginates_class_results(): void
    {
        $heavyArmor = ProficiencyType::factory()->create(['name' => 'Heavy Armor', 'category' => 'armor']);

        $this->createMultipleEntities(15, function () use ($heavyArmor) {
            $class = CharacterClass::factory()->create();
            Proficiency::factory()->create(['reference_type' => CharacterClass::class, 'reference_id' => $class->id, 'proficiency_type_id' => $heavyArmor->id]);
            return $class;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/proficiency-types/{$heavyArmor->id}/classes?per_page=10", 10, 15, 10);
    }

    // ========================================
    // Races Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_races_for_proficiency_type(): void
    {
        $elvish = ProficiencyType::factory()->create(['name' => 'Elvish', 'category' => 'language']);

        $elf = Race::factory()->create(['name' => 'Elf']);
        $halfElf = Race::factory()->create(['name' => 'Half-Elf']);
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        Proficiency::factory()->create(['reference_type' => Race::class, 'reference_id' => $elf->id, 'proficiency_type_id' => $elvish->id]);
        Proficiency::factory()->create(['reference_type' => Race::class, 'reference_id' => $halfElf->id, 'proficiency_type_id' => $elvish->id]);

        $response = $this->getJson("/api/v1/lookups/proficiency-types/{$elvish->id}/races");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Elf'])
            ->assertJsonFragment(['name' => 'Half-Elf'])
            ->assertJsonMissing(['name' => 'Dwarf']);
    }

    #[Test]
    public function it_returns_empty_when_proficiency_type_has_no_races(): void
    {
        $greatsword = ProficiencyType::factory()->create(['name' => 'Greatsword', 'category' => 'weapon']);

        $this->assertReturnsEmpty("/api/v1/lookups/proficiency-types/{$greatsword->id}/races");
    }

    #[Test]
    public function it_accepts_name_for_races_endpoint(): void
    {
        $dwarven = ProficiencyType::factory()->create(['name' => 'Dwarvish', 'slug' => 'dwarvish', 'category' => 'language']);
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        Proficiency::factory()->create(['reference_type' => Race::class, 'reference_id' => $dwarf->id, 'proficiency_type_id' => $dwarven->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/Dwarvish/races');

        $response->assertOk()->assertJsonFragment(['name' => 'Dwarf']);
    }

    #[Test]
    public function it_accepts_slug_for_races_endpoint(): void
    {
        $elvish = ProficiencyType::factory()->create(['name' => 'Elvish', 'slug' => 'elvish', 'category' => 'language']);
        $elf = Race::factory()->create(['name' => 'Elf']);
        Proficiency::factory()->create(['reference_type' => Race::class, 'reference_id' => $elf->id, 'proficiency_type_id' => $elvish->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/elvish/races');

        $response->assertOk()->assertJsonFragment(['name' => 'Elf']);
    }

    #[Test]
    public function it_paginates_race_results(): void
    {
        $darkvision = ProficiencyType::factory()->create(['name' => 'Darkvision', 'category' => 'trait']);

        $this->createMultipleEntities(12, function () use ($darkvision) {
            $race = Race::factory()->create();
            Proficiency::factory()->create(['reference_type' => Race::class, 'reference_id' => $race->id, 'proficiency_type_id' => $darkvision->id]);
            return $race;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/proficiency-types/{$darkvision->id}/races?per_page=5", 5, 12, 5);
    }

    // ========================================
    // Backgrounds Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_backgrounds_for_proficiency_type(): void
    {
        $stealth = ProficiencyType::factory()->create(['name' => 'Stealth', 'category' => 'skill']);

        $criminal = Background::factory()->create(['name' => 'Criminal']);
        $urchin = Background::factory()->create(['name' => 'Urchin']);
        $noble = Background::factory()->create(['name' => 'Noble']);

        Proficiency::factory()->create(['reference_type' => Background::class, 'reference_id' => $criminal->id, 'proficiency_type_id' => $stealth->id]);
        Proficiency::factory()->create(['reference_type' => Background::class, 'reference_id' => $urchin->id, 'proficiency_type_id' => $stealth->id]);

        $response = $this->getJson("/api/v1/lookups/proficiency-types/{$stealth->id}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Criminal'])
            ->assertJsonFragment(['name' => 'Urchin'])
            ->assertJsonMissing(['name' => 'Noble']);
    }

    #[Test]
    public function it_returns_empty_when_proficiency_type_has_no_backgrounds(): void
    {
        $battleaxe = ProficiencyType::factory()->create(['name' => 'Battleaxe', 'category' => 'weapon']);

        $this->assertReturnsEmpty("/api/v1/lookups/proficiency-types/{$battleaxe->id}/backgrounds");
    }

    #[Test]
    public function it_accepts_name_for_backgrounds_endpoint(): void
    {
        $deception = ProficiencyType::factory()->create(['name' => 'Deception', 'slug' => 'deception', 'category' => 'skill']);
        $charlatan = Background::factory()->create(['name' => 'Charlatan']);
        Proficiency::factory()->create(['reference_type' => Background::class, 'reference_id' => $charlatan->id, 'proficiency_type_id' => $deception->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/Deception/backgrounds');

        $response->assertOk()->assertJsonFragment(['name' => 'Charlatan']);
    }

    #[Test]
    public function it_accepts_slug_for_backgrounds_endpoint(): void
    {
        $stealth = ProficiencyType::factory()->create(['name' => 'Stealth', 'slug' => 'stealth', 'category' => 'skill']);
        $criminal = Background::factory()->create(['name' => 'Criminal']);
        Proficiency::factory()->create(['reference_type' => Background::class, 'reference_id' => $criminal->id, 'proficiency_type_id' => $stealth->id]);

        $response = $this->getJson('/api/v1/lookups/proficiency-types/stealth/backgrounds');

        $response->assertOk()->assertJsonFragment(['name' => 'Criminal']);
    }

    #[Test]
    public function it_paginates_background_results(): void
    {
        $insight = ProficiencyType::factory()->create(['name' => 'Insight', 'category' => 'skill']);

        $this->createMultipleEntities(8, function () use ($insight) {
            $background = Background::factory()->create();
            Proficiency::factory()->create(['reference_type' => Background::class, 'reference_id' => $background->id, 'proficiency_type_id' => $insight->id]);
            return $background;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/proficiency-types/{$insight->id}/backgrounds?per_page=3", 3, 8, 3);
    }
}
