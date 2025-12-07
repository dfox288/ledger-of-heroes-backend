<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Monster;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for searchable_options in pagination meta.
 */
#[Group('feature-db')]
class SearchableOptionsMetaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function spells_index_includes_searchable_options_in_meta(): void
    {
        $school = SpellSchool::first() ?? SpellSchool::factory()->create();
        Spell::factory()->create(['spell_school_id' => $school->id]);

        $response = $this->getJson('/api/v1/spells');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links',
            'meta' => [
                'searchable_options' => [
                    'filterable_attributes',
                    'sortable_attributes',
                ],
            ],
        ]);

        // Verify some expected filterable attributes are present
        $filterableAttrs = $response->json('meta.searchable_options.filterable_attributes');
        $this->assertContains('level', $filterableAttrs);
        $this->assertContains('school_code', $filterableAttrs);
    }

    #[Test]
    public function monsters_index_includes_searchable_options_in_meta(): void
    {
        Monster::factory()->create();

        $response = $this->getJson('/api/v1/monsters');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links',
            'meta' => [
                'searchable_options' => [
                    'filterable_attributes',
                    'sortable_attributes',
                ],
            ],
        ]);

        // Verify some expected filterable attributes
        $filterableAttrs = $response->json('meta.searchable_options.filterable_attributes');
        $this->assertContains('challenge_rating', $filterableAttrs);
        $this->assertContains('type', $filterableAttrs);
    }

    #[Test]
    public function classes_index_includes_searchable_options_in_meta(): void
    {
        CharacterClass::factory()->create();

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links',
            'meta' => [
                'searchable_options' => [
                    'filterable_attributes',
                    'sortable_attributes',
                ],
            ],
        ]);

        // Verify some expected filterable attributes
        $filterableAttrs = $response->json('meta.searchable_options.filterable_attributes');
        $this->assertContains('hit_die', $filterableAttrs);
    }
}
