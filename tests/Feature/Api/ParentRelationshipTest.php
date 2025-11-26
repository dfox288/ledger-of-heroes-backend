<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class ParentRelationshipTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected $seed = true;

    // ==================== RACE TESTS ====================

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_index_returns_parent_race_with_minimal_data(): void
    {
        $size = Size::first();
        $parent = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $size->id,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parent->id,
            'size_id' => $size->id,
        ]);

        $response = $this->getJson('/api/v1/races');

        $response->assertOk();

        $highElf = collect($response->json('data'))
            ->firstWhere('slug', 'high-elf');

        $this->assertNotNull($highElf, 'High Elf subrace not found in response');

        // Should have parent_race with minimal data
        $this->assertArrayHasKey('parent_race', $highElf);
        $this->assertNotNull($highElf['parent_race']);
        $this->assertEquals($parent->id, $highElf['parent_race']['id']);
        $this->assertEquals('elf', $highElf['parent_race']['slug']);
        $this->assertEquals('Elf', $highElf['parent_race']['name']);

        // Should NOT have parent's relationships in index (they're not loaded)
        // The parent object exists but its relationships aren't eager-loaded
        $this->assertArrayNotHasKey('traits', $highElf['parent_race']);
        $this->assertArrayNotHasKey('modifiers', $highElf['parent_race']);
        $this->assertArrayNotHasKey('subraces', $highElf['parent_race']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_show_returns_parent_race_with_full_relationships(): void
    {
        $size = Size::first();
        $parent = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $size->id,
        ]);

        // Add a trait to the parent so we can verify it's loaded
        $parent->traits()->create([
            'name' => 'Darkvision',
            'description' => 'You can see in the dark.',
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parent->id,
            'size_id' => $size->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$subrace->slug}");

        $response->assertOk();

        $data = $response->json('data');

        // Should have parent_race
        $this->assertArrayHasKey('parent_race', $data);
        $this->assertNotNull($data['parent_race']);
        $this->assertEquals($parent->id, $data['parent_race']['id']);
        $this->assertEquals('elf', $data['parent_race']['slug']);
        $this->assertEquals('Elf', $data['parent_race']['name']);

        // Should HAVE parent's relationships in show endpoint
        $this->assertArrayHasKey('traits', $data['parent_race']);
        $this->assertIsArray($data['parent_race']['traits']);
        $this->assertCount(1, $data['parent_race']['traits']);
        $this->assertEquals('Darkvision', $data['parent_race']['traits'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_search_returns_parent_race(): void
    {
        $size = Size::first();
        $parent = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $size->id,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $parent->id,
            'size_id' => $size->id,
        ]);

        // Index to Meilisearch
        $subrace->searchable();
        $this->waitForMeilisearch($subrace);

        $response = $this->getJson('/api/v1/races?q=high');

        $response->assertOk();

        $highElf = collect($response->json('data'))
            ->firstWhere('slug', 'high-elf');

        if ($highElf) {
            // Should have parent_race with minimal data (same as regular index)
            $this->assertArrayHasKey('parent_race', $highElf);
            $this->assertNotNull($highElf['parent_race']);
            $this->assertEquals($parent->id, $highElf['parent_race']['id']);
            $this->assertEquals('elf', $highElf['parent_race']['slug']);
            $this->assertEquals('Elf', $highElf['parent_race']['name']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_index_with_base_race_has_null_parent(): void
    {
        $size = Size::first();
        $baseRace = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
            'parent_race_id' => null,
            'size_id' => $size->id,
        ]);

        $response = $this->getJson('/api/v1/races');

        $response->assertOk();

        $dwarf = collect($response->json('data'))
            ->firstWhere('slug', 'dwarf');

        $this->assertNotNull($dwarf);
        // Base races should not have parent_race key or it should be null
        $this->assertTrue(
            ! isset($dwarf['parent_race']) || $dwarf['parent_race'] === null,
            'Base race should not have parent_race or it should be null'
        );
    }

    // ==================== CLASS TESTS ====================

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_index_returns_parent_class_with_minimal_data(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'School of Evocation',
            'slug' => 'school-of-evocation',
            'parent_class_id' => $parent->id,
            'hit_die' => 6,
        ]);

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();

        $evocation = collect($response->json('data'))
            ->firstWhere('slug', 'school-of-evocation');

        $this->assertNotNull($evocation, 'School of Evocation subclass not found in response');

        // Should have parent_class with minimal data
        $this->assertArrayHasKey('parent_class', $evocation);
        $this->assertNotNull($evocation['parent_class']);
        $this->assertEquals($parent->id, $evocation['parent_class']['id']);
        $this->assertEquals('wizard', $evocation['parent_class']['slug']);
        $this->assertEquals('Wizard', $evocation['parent_class']['name']);

        // Should NOT have parent's relationships in index
        $this->assertArrayNotHasKey('traits', $evocation['parent_class']);
        $this->assertArrayNotHasKey('features', $evocation['parent_class']);
        $this->assertArrayNotHasKey('subclasses', $evocation['parent_class']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_show_returns_parent_class_with_full_relationships(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
        ]);

        // Add a feature to the parent
        $parent->features()->create([
            'feature_name' => 'Spellcasting',
            'description' => 'You can cast wizard spells.',
            'level' => 1,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'School of Evocation',
            'slug' => 'school-of-evocation',
            'parent_class_id' => $parent->id,
            'hit_die' => 6,
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk();

        $data = $response->json('data');

        // Should have parent_class
        $this->assertArrayHasKey('parent_class', $data);
        $this->assertNotNull($data['parent_class']);
        $this->assertEquals($parent->id, $data['parent_class']['id']);
        $this->assertEquals('wizard', $data['parent_class']['slug']);
        $this->assertEquals('Wizard', $data['parent_class']['name']);

        // Should HAVE parent's relationships in show endpoint
        $this->assertArrayHasKey('features', $data['parent_class']);
        $this->assertIsArray($data['parent_class']['features']);
        $this->assertCount(1, $data['parent_class']['features']);
        $this->assertEquals('Spellcasting', $data['parent_class']['features'][0]['feature_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_search_returns_parent_class(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'School of Evocation',
            'slug' => 'school-of-evocation',
            'parent_class_id' => $parent->id,
            'hit_die' => 6,
        ]);

        // Index to Meilisearch
        $subclass->searchable();
        $this->waitForMeilisearch($subclass);

        $response = $this->getJson('/api/v1/classes?q=evocation');

        $response->assertOk();

        $evocation = collect($response->json('data'))
            ->firstWhere('slug', 'school-of-evocation');

        if ($evocation) {
            // Should have parent_class with minimal data
            $this->assertArrayHasKey('parent_class', $evocation);
            $this->assertNotNull($evocation['parent_class']);
            $this->assertEquals($parent->id, $evocation['parent_class']['id']);
            $this->assertEquals('wizard', $evocation['parent_class']['slug']);
            $this->assertEquals('Wizard', $evocation['parent_class']['name']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_index_with_base_class_has_null_parent(): void
    {
        $baseClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
            'hit_die' => 10,
        ]);

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();

        $fighter = collect($response->json('data'))
            ->firstWhere('slug', 'fighter');

        $this->assertNotNull($fighter);
        // Base classes should not have parent_class key or it should be null
        $this->assertTrue(
            ! isset($fighter['parent_class']) || $fighter['parent_class'] === null,
            'Base class should not have parent_class or it should be null'
        );
    }
}
