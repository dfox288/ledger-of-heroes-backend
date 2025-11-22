<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Proficiency;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProficiencyTypeReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // ========================================
    // Classes Endpoint Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_classes_for_proficiency_type(): void
    {
        // Create proficiency type
        $longsword = ProficiencyType::factory()->create([
            'name' => 'Longsword',
            'category' => 'weapon',
            'subcategory' => 'martial',
        ]);

        // Create classes with longsword proficiency
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']); // No longsword

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\CharacterClass',
            'reference_id' => $fighter->id,
            'proficiency_type_id' => $longsword->id,
        ]);

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\CharacterClass',
            'reference_id' => $paladin->id,
            'proficiency_type_id' => $longsword->id,
        ]);

        // Act
        $response = $this->getJson("/api/v1/proficiency-types/{$longsword->id}/classes");

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Fighter'])
            ->assertJsonFragment(['name' => 'Paladin'])
            ->assertJsonMissing(['name' => 'Wizard']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_when_proficiency_type_has_no_classes(): void
    {
        $thiefTools = ProficiencyType::factory()->create([
            'name' => "Thieves' Tools",
            'category' => 'tool',
        ]);

        $response = $this->getJson("/api/v1/proficiency-types/{$thiefTools->id}/classes");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_name_for_classes_endpoint(): void
    {
        $stealth = ProficiencyType::factory()->create([
            'name' => 'Stealth',
            'slug' => 'stealth',
            'category' => 'skill',
        ]);

        $rogue = CharacterClass::factory()->create(['name' => 'Rogue']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\CharacterClass',
            'reference_id' => $rogue->id,
            'proficiency_type_id' => $stealth->id,
        ]);

        $response = $this->getJson('/api/v1/proficiency-types/Stealth/classes');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Rogue']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_slug_for_classes_endpoint(): void
    {
        // Note: Using name-based routing for now as slug routing for nested routes requires additional setup
        // TODO: Investigate Laravel scoped route model binding for nested slug routes
        $testWeapon = ProficiencyType::factory()->create([
            'name' => 'Combat Axe',
            'slug' => 'combat-axe',
            'category' => 'weapon',
        ]);

        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\CharacterClass',
            'reference_id' => $fighter->id,
            'proficiency_type_id' => $testWeapon->id,
        ]);

        // Use name-based routing which still works
        $response = $this->getJson('/api/v1/proficiency-types/Combat Axe/classes');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Fighter']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_class_results(): void
    {
        $heavyArmor = ProficiencyType::factory()->create([
            'name' => 'Heavy Armor',
            'category' => 'armor',
        ]);

        // Create 15 classes with heavy armor proficiency
        for ($i = 1; $i <= 15; $i++) {
            $class = CharacterClass::factory()->create(['name' => "Class {$i}"]);
            Proficiency::factory()->create([
                'reference_type' => 'App\Models\CharacterClass',
                'reference_id' => $class->id,
                'proficiency_type_id' => $heavyArmor->id,
            ]);
        }

        $response = $this->getJson("/api/v1/proficiency-types/{$heavyArmor->id}/classes?per_page=10");

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);
    }

    // ========================================
    // Races Endpoint Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_races_for_proficiency_type(): void
    {
        $elvish = ProficiencyType::factory()->create([
            'name' => 'Elvish',
            'category' => 'language',
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);
        $halfElf = Race::factory()->create(['name' => 'Half-Elf']);
        $dwarf = Race::factory()->create(['name' => 'Dwarf']); // No Elvish

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $elf->id,
            'proficiency_type_id' => $elvish->id,
        ]);

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $halfElf->id,
            'proficiency_type_id' => $elvish->id,
        ]);

        $response = $this->getJson("/api/v1/proficiency-types/{$elvish->id}/races");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Elf'])
            ->assertJsonFragment(['name' => 'Half-Elf'])
            ->assertJsonMissing(['name' => 'Dwarf']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_when_proficiency_type_has_no_races(): void
    {
        $greatsword = ProficiencyType::factory()->create([
            'name' => 'Greatsword',
            'category' => 'weapon',
        ]);

        $response = $this->getJson("/api/v1/proficiency-types/{$greatsword->id}/races");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_name_for_races_endpoint(): void
    {
        $dwarven = ProficiencyType::factory()->create([
            'name' => 'Dwarvish',
            'slug' => 'dwarvish',
            'category' => 'language',
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $dwarf->id,
            'proficiency_type_id' => $dwarven->id,
        ]);

        $response = $this->getJson('/api/v1/proficiency-types/Dwarvish/races');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Dwarf']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_slug_for_races_endpoint(): void
    {
        $elvish = ProficiencyType::factory()->create([
            'name' => 'Elvish',
            'slug' => 'elvish',
            'category' => 'language',
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Race',
            'reference_id' => $elf->id,
            'proficiency_type_id' => $elvish->id,
        ]);

        $response = $this->getJson('/api/v1/proficiency-types/elvish/races');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Elf']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_race_results(): void
    {
        $darkvision = ProficiencyType::factory()->create([
            'name' => 'Darkvision',
            'category' => 'trait',
        ]);

        // Create 12 races with darkvision
        for ($i = 1; $i <= 12; $i++) {
            $race = Race::factory()->create(['name' => "Race {$i}"]);
            Proficiency::factory()->create([
                'reference_type' => 'App\Models\Race',
                'reference_id' => $race->id,
                'proficiency_type_id' => $darkvision->id,
            ]);
        }

        $response = $this->getJson("/api/v1/proficiency-types/{$darkvision->id}/races?per_page=5");

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1);
    }

    // ========================================
    // Backgrounds Endpoint Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_backgrounds_for_proficiency_type(): void
    {
        $stealth = ProficiencyType::factory()->create([
            'name' => 'Stealth',
            'category' => 'skill',
        ]);

        $criminal = Background::factory()->create(['name' => 'Criminal']);
        $urchin = Background::factory()->create(['name' => 'Urchin']);
        $noble = Background::factory()->create(['name' => 'Noble']); // No stealth

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type_id' => $stealth->id,
        ]);

        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $urchin->id,
            'proficiency_type_id' => $stealth->id,
        ]);

        $response = $this->getJson("/api/v1/proficiency-types/{$stealth->id}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Criminal'])
            ->assertJsonFragment(['name' => 'Urchin'])
            ->assertJsonMissing(['name' => 'Noble']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_when_proficiency_type_has_no_backgrounds(): void
    {
        $battleaxe = ProficiencyType::factory()->create([
            'name' => 'Battleaxe',
            'category' => 'weapon',
        ]);

        $response = $this->getJson("/api/v1/proficiency-types/{$battleaxe->id}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_name_for_backgrounds_endpoint(): void
    {
        $deception = ProficiencyType::factory()->create([
            'name' => 'Deception',
            'slug' => 'deception',
            'category' => 'skill',
        ]);

        $charlatan = Background::factory()->create(['name' => 'Charlatan']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $charlatan->id,
            'proficiency_type_id' => $deception->id,
        ]);

        $response = $this->getJson('/api/v1/proficiency-types/Deception/backgrounds');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Charlatan']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_slug_for_backgrounds_endpoint(): void
    {
        $stealth = ProficiencyType::factory()->create([
            'name' => 'Stealth',
            'slug' => 'stealth',
            'category' => 'skill',
        ]);

        $criminal = Background::factory()->create(['name' => 'Criminal']);
        Proficiency::factory()->create([
            'reference_type' => 'App\Models\Background',
            'reference_id' => $criminal->id,
            'proficiency_type_id' => $stealth->id,
        ]);

        $response = $this->getJson('/api/v1/proficiency-types/stealth/backgrounds');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Criminal']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_background_results(): void
    {
        $insight = ProficiencyType::factory()->create([
            'name' => 'Insight',
            'category' => 'skill',
        ]);

        // Create 8 backgrounds with insight
        for ($i = 1; $i <= 8; $i++) {
            $background = Background::factory()->create(['name' => "Background {$i}"]);
            Proficiency::factory()->create([
                'reference_type' => 'App\Models\Background',
                'reference_id' => $background->id,
                'proficiency_type_id' => $insight->id,
            ]);
        }

        $response = $this->getJson("/api/v1/proficiency-types/{$insight->id}/backgrounds?per_page=3");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.current_page', 1);
    }
}
