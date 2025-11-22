<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Spell;
use App\Models\SpellEffect;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellDamageEffectFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed necessary lookup data only once
        if (\App\Models\Source::count() === 0) {
            $this->seed(\Database\Seeders\SourceSeeder::class);
        }
        if (\App\Models\SpellSchool::count() === 0) {
            $this->seed(\Database\Seeders\SpellSchoolSeeder::class);
        }
        if (\App\Models\DamageType::count() === 0) {
            $this->seed(\Database\Seeders\DamageTypeSeeder::class);
        }
        if (\App\Models\AbilityScore::count() === 0) {
            $this->seed(\Database\Seeders\AbilityScoreSeeder::class);
        }
    }

    private function createSpellWithDamageType(string $damageTypeCode, array $attributes = []): Spell
    {
        $damageType = DamageType::where('code', $damageTypeCode)->firstOrFail();
        $spell = Spell::factory()->create(array_merge([
            'components' => 'V, S',
        ], $attributes));

        SpellEffect::factory()->create([
            'spell_id' => $spell->id,
            'damage_type_id' => $damageType->id,
            'effect_type' => 'damage',
        ]);

        return $spell;
    }

    private function createSpellWithSavingThrow(string $abilityCode, array $attributes = []): Spell
    {
        $ability = AbilityScore::where('code', $abilityCode)->firstOrFail();
        $spell = Spell::factory()->create(array_merge([
            'components' => 'V, S',
        ], $attributes));

        $spell->savingThrows()->attach($ability->id, [
            'save_effect' => 'half_damage',
            'is_initial_save' => true,
        ]);

        return $spell;
    }

    private function createSpellWithComponents(string $components): Spell
    {
        return Spell::factory()->create([
            'components' => $components,
        ]);
    }

    // ===================================
    // Damage Type Filtering Tests (4)
    // ===================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_by_single_damage_type(): void
    {
        // Create test spells
        $fireSpell = $this->createSpellWithDamageType('F'); // Fire
        $coldSpell = $this->createSpellWithDamageType('C'); // Cold

        $response = $this->getJson('/api/v1/spells?damage_type=fire');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($fireSpell->id, $spellIds, 'Fire spell not found in filtered results');
        $this->assertNotContains($coldSpell->id, $spellIds, 'Cold spell should not be in fire results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_by_multiple_damage_types(): void
    {
        // Create test spells
        $fireSpell = $this->createSpellWithDamageType('F'); // Fire
        $coldSpell = $this->createSpellWithDamageType('C'); // Cold
        $acidSpell = $this->createSpellWithDamageType('A'); // Acid

        $response = $this->getJson('/api/v1/spells?damage_type=fire,cold');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($fireSpell->id, $spellIds, 'Fire spell not found');
        $this->assertContains($coldSpell->id, $spellIds, 'Cold spell not found');
        $this->assertNotContains($acidSpell->id, $spellIds, 'Acid spell should not be in results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_damage_type_with_level_filter(): void
    {
        // Create low-level and high-level fire spells
        $lowLevelFire = $this->createSpellWithDamageType('F', ['level' => 2]); // Fire
        $highLevelFire = $this->createSpellWithDamageType('F', ['level' => 7]); // Fire

        $response = $this->getJson('/api/v1/spells?damage_type=fire&level=2');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'No results returned for low-level fire spells');

        $spellIds = collect($data)->pluck('id')->all();
        $this->assertContains($lowLevelFire->id, $spellIds, 'Low-level fire spell not found');
        $this->assertNotContains($highLevelFire->id, $spellIds, 'High-level fire spell should not be in results');

        // Verify all results are the correct level
        foreach ($data as $spellData) {
            $this->assertEquals(2, $spellData['level'], "Spell {$spellData['name']} has wrong level");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_results_for_nonexistent_damage_type(): void
    {
        $this->createSpellWithDamageType('F'); // Fire

        $response = $this->getJson('/api/v1/spells?damage_type=nonexistent');

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    // ===================================
    // Saving Throw Filtering Tests (4)
    // ===================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_by_single_saving_throw(): void
    {
        // Create test spells
        $dexSpell = $this->createSpellWithSavingThrow('DEX');
        $wisSpell = $this->createSpellWithSavingThrow('WIS');

        $response = $this->getJson('/api/v1/spells?saving_throw=DEX');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($dexSpell->id, $spellIds, 'DEX save spell not found in filtered results');
        $this->assertNotContains($wisSpell->id, $spellIds, 'WIS save spell should not be in DEX results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_by_multiple_saving_throws(): void
    {
        // Create test spells
        $dexSpell = $this->createSpellWithSavingThrow('DEX');
        $conSpell = $this->createSpellWithSavingThrow('CON');
        $wisSpell = $this->createSpellWithSavingThrow('WIS');

        $response = $this->getJson('/api/v1/spells?saving_throw=DEX,CON');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($dexSpell->id, $spellIds, 'DEX save spell not found');
        $this->assertContains($conSpell->id, $spellIds, 'CON save spell not found');
        $this->assertNotContains($wisSpell->id, $spellIds, 'WIS save spell should not be in results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_saving_throw_with_school_filter(): void
    {
        $enchantmentSchool = SpellSchool::where('code', 'EN')->first();

        // Create enchantment WIS save spell and evocation WIS save spell
        $enchantmentWis = $this->createSpellWithSavingThrow('WIS', [
            'spell_school_id' => $enchantmentSchool->id,
        ]);
        $otherSchool = SpellSchool::where('code', '!=', 'EN')->first();
        $otherWis = $this->createSpellWithSavingThrow('WIS', [
            'spell_school_id' => $otherSchool->id,
        ]);

        $response = $this->getJson("/api/v1/spells?saving_throw=WIS&school={$enchantmentSchool->id}");

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($enchantmentWis->id, $spellIds, 'Enchantment WIS spell not found');
        $this->assertNotContains($otherWis->id, $spellIds, 'Non-enchantment WIS spell should not be in results');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_by_mental_saving_throws(): void
    {
        // Create spells with mental saves (INT, WIS, CHA)
        $intSpell = $this->createSpellWithSavingThrow('INT');
        $wisSpell = $this->createSpellWithSavingThrow('WIS');
        $chaSpell = $this->createSpellWithSavingThrow('CHA');
        $dexSpell = $this->createSpellWithSavingThrow('DEX');

        $response = $this->getJson('/api/v1/spells?saving_throw=INT,WIS,CHA');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($intSpell->id, $spellIds, 'INT spell not found');
        $this->assertContains($wisSpell->id, $spellIds, 'WIS spell not found');
        $this->assertContains($chaSpell->id, $spellIds, 'CHA spell not found');
        $this->assertNotContains($dexSpell->id, $spellIds, 'DEX spell should not be in mental saves');
    }

    // ===================================
    // Component Filtering Tests (4)
    // ===================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_requiring_verbal_components(): void
    {
        // Create spells with and without verbal component
        $verbalSpell = $this->createSpellWithComponents('V, S');
        $nonVerbalSpell = $this->createSpellWithComponents('S, M');

        $response = $this->getJson('/api/v1/spells?requires_verbal=true');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($verbalSpell->id, $spellIds, 'Verbal spell not found');
        $this->assertNotContains($nonVerbalSpell->id, $spellIds, 'Non-verbal spell should not be in results');

        // Verify all results have verbal component
        foreach ($response->json('data') as $spellData) {
            $fullSpell = Spell::find($spellData['id']);
            $this->assertStringContainsString('V', $fullSpell->components, "Spell {$spellData['name']} missing verbal component");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_requiring_somatic_components(): void
    {
        // Create spells with and without somatic component
        $somaticSpell = $this->createSpellWithComponents('V, S');
        $nonSomaticSpell = $this->createSpellWithComponents('V, M');

        $response = $this->getJson('/api/v1/spells?requires_somatic=true');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($somaticSpell->id, $spellIds, 'Somatic spell not found');
        $this->assertNotContains($nonSomaticSpell->id, $spellIds, 'Non-somatic spell should not be in results');

        // Verify all results have somatic component
        foreach ($response->json('data') as $spellData) {
            $fullSpell = Spell::find($spellData['id']);
            $this->assertStringContainsString('S', $fullSpell->components, "Spell {$spellData['name']} missing somatic component");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_requiring_material_components(): void
    {
        // Create spells with and without material component
        $materialSpell = $this->createSpellWithComponents('V, S, M');
        $nonMaterialSpell = $this->createSpellWithComponents('V, S');

        $response = $this->getJson('/api/v1/spells?requires_material=true');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($materialSpell->id, $spellIds, 'Material spell not found');
        $this->assertNotContains($nonMaterialSpell->id, $spellIds, 'Non-material spell should not be in results');

        // Verify all results have material component
        foreach ($response->json('data') as $spellData) {
            $fullSpell = Spell::find($spellData['id']);
            $this->assertStringContainsString('M', $fullSpell->components, "Spell {$spellData['name']} missing material component");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_spells_without_verbal_components_for_silent_casting(): void
    {
        // Create spells with and without verbal component
        $silentSpell = $this->createSpellWithComponents('S, M');
        $verbalSpell = $this->createSpellWithComponents('V, S, M');

        $response = $this->getJson('/api/v1/spells?requires_verbal=false');

        $response->assertOk();

        $spellIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($silentSpell->id, $spellIds, 'Silent spell not found');
        $this->assertNotContains($verbalSpell->id, $spellIds, 'Verbal spell should not be in silent results');

        // Verify all results DON'T have verbal component
        foreach ($response->json('data') as $spellData) {
            $fullSpell = Spell::find($spellData['id']);
            $this->assertStringNotContainsString('V', $fullSpell->components, "Spell {$spellData['name']} has verbal component");
        }
    }
}
