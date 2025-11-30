<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ClassFeatureSpellsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function class_api_includes_feature_spells(): void
    {
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'level' => 1,
        ]);

        $bless = Spell::factory()->create(['name' => 'Bless', 'level' => 1]);
        $cureWounds = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $bless->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);
        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $cureWounds->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $response = $this->getJson("/api/v1/classes/{$lifeDomain->slug}");

        $response->assertStatus(200);

        // Find the feature in response
        $features = $response->json('data.features');
        $domainFeature = collect($features)->firstWhere('feature_name', 'Divine Domain: Life Domain');

        $this->assertNotNull($domainFeature);
        $this->assertArrayHasKey('spells', $domainFeature);
        $this->assertCount(2, $domainFeature['spells']);
        $this->assertArrayHasKey('is_always_prepared', $domainFeature);
        $this->assertTrue($domainFeature['is_always_prepared']);
    }

    #[Test]
    public function feature_spells_include_level_requirement(): void
    {
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'level' => 1,
        ]);

        $lesserRestoration = Spell::factory()->create(['name' => 'Lesser Restoration', 'level' => 2]);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $lesserRestoration->id,
            'level_requirement' => 3,
            'is_cantrip' => false,
        ]);

        $response = $this->getJson("/api/v1/classes/{$lifeDomain->slug}");

        $response->assertStatus(200);

        $features = $response->json('data.features');
        $domainFeature = collect($features)->firstWhere('feature_name', 'Divine Domain: Life Domain');
        $spell = $domainFeature['spells'][0];

        $this->assertEquals(3, $spell['level_requirement']);
        $this->assertEquals('Lesser Restoration', $spell['spell']['name']);
    }
}
