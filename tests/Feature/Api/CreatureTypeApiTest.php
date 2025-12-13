<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CreatureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for CreatureTypeController.
 *
 * @see \App\Http\Controllers\Api\CreatureTypeController
 */
#[Group('feature-db')]
class CreatureTypeApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_all_creature_types(): void
    {
        CreatureType::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'typically_immune_to_poison',
                        'typically_immune_to_charmed',
                        'typically_immune_to_frightened',
                        'typically_immune_to_exhaustion',
                        'requires_sustenance',
                        'requires_sleep',
                        'description',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_returns_creature_types_ordered_alphabetically(): void
    {
        CreatureType::factory()->create(['name' => 'Undead']);
        CreatureType::factory()->create(['name' => 'Aberration']);
        CreatureType::factory()->create(['name' => 'Giant']);

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertEquals(['Aberration', 'Giant', 'Undead'], $names);
    }

    #[Test]
    public function it_returns_empty_data_when_no_creature_types_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function it_returns_correct_boolean_fields_for_undead(): void
    {
        CreatureType::factory()->undead()->create();

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk();

        $undead = collect($response->json('data'))->firstWhere('name', 'Undead');

        $this->assertNotNull($undead);
        $this->assertTrue($undead['typically_immune_to_poison']);
        $this->assertTrue($undead['typically_immune_to_charmed']);
        $this->assertFalse($undead['typically_immune_to_frightened']);
        $this->assertTrue($undead['typically_immune_to_exhaustion']);
        $this->assertFalse($undead['requires_sustenance']);
        $this->assertFalse($undead['requires_sleep']);
    }

    #[Test]
    public function it_returns_correct_boolean_fields_for_construct(): void
    {
        CreatureType::factory()->construct()->create();

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk();

        $construct = collect($response->json('data'))->firstWhere('name', 'Construct');

        $this->assertNotNull($construct);
        $this->assertTrue($construct['typically_immune_to_poison']);
        $this->assertTrue($construct['typically_immune_to_charmed']);
        $this->assertTrue($construct['typically_immune_to_frightened']);
        $this->assertTrue($construct['typically_immune_to_exhaustion']);
        $this->assertFalse($construct['requires_sustenance']);
        $this->assertFalse($construct['requires_sleep']);
    }

    #[Test]
    public function it_returns_correct_boolean_fields_for_standard_creature(): void
    {
        CreatureType::factory()->create([
            'name' => 'Beast',
            'slug' => 'core:beast',
            'typically_immune_to_poison' => false,
            'typically_immune_to_charmed' => false,
            'typically_immune_to_frightened' => false,
            'typically_immune_to_exhaustion' => false,
            'requires_sustenance' => true,
            'requires_sleep' => true,
        ]);

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk();

        $beast = collect($response->json('data'))->firstWhere('name', 'Beast');

        $this->assertNotNull($beast);
        $this->assertFalse($beast['typically_immune_to_poison']);
        $this->assertFalse($beast['typically_immune_to_charmed']);
        $this->assertFalse($beast['typically_immune_to_frightened']);
        $this->assertFalse($beast['typically_immune_to_exhaustion']);
        $this->assertTrue($beast['requires_sustenance']);
        $this->assertTrue($beast['requires_sleep']);
    }

    #[Test]
    public function it_includes_description_field(): void
    {
        CreatureType::factory()->create([
            'name' => 'Dragon',
            'description' => 'Ancient winged reptilian creatures.',
        ]);

        $response = $this->getJson('/api/v1/lookups/creature-types');

        $response->assertOk();

        $dragon = collect($response->json('data'))->firstWhere('name', 'Dragon');

        $this->assertNotNull($dragon);
        $this->assertEquals('Ancient winged reptilian creatures.', $dragon['description']);
    }
}
