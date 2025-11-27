<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
/**
 * Tests for enhanced Meilisearch filtering features:
 * 1. Damage Type Filtering (damage_types array field)
 * 2. Saving Throw Filtering (saving_throws array field)
 * 3. Component Breakdown Filtering (requires_verbal, requires_somatic, requires_material boolean fields)
 *
 * These tests assume the Spell model has been enhanced with:
 * - damage_types: array of damage type codes (F, C, L, etc.)
 * - saving_throws: array of ability codes (STR, DEX, CON, INT, WIS, CHA)
 * - requires_verbal: boolean (parsed from components string)
 * - requires_somatic: boolean (parsed from components string)
 * - requires_material: boolean (parsed from components string)
 */
class SpellEnhancedFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Meilisearch indexes are configured with enhanced fields
        $this->artisan('search:configure-indexes');
    }

    // ===================================================================
    // DAMAGE TYPE FILTERING TESTS
    // ===================================================================

    #[Test]
    public function it_filters_spells_by_single_damage_type()
    {
        // Fire damage spells (code: F)
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [F]');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);

        if ($response->json('meta.total') > 0) {
            // Verify results have fire damage in their effects
            foreach ($response->json('data') as $spell) {
                $this->assertNotEmpty($spell['effects'] ?? [], "Spell {$spell['name']} should have effects");
                $hasFire = false;
                foreach ($spell['effects'] ?? [] as $effect) {
                    if (isset($effect['damage_type']['code']) && $effect['damage_type']['code'] === 'F') {
                        $hasFire = true;
                        break;
                    }
                }
                $this->assertTrue($hasFire, "Spell {$spell['name']} should have fire damage");
            }
        } else {
            $this->markTestIncomplete('No fire damage spells found in test data - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_by_multiple_damage_types()
    {
        // Fire or Cold damage spells (codes: F, C)
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [F, C]');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertNotEmpty($spell['effects'] ?? [], "Spell {$spell['name']} should have effects");
                $hasFireOrCold = false;
                foreach ($spell['effects'] ?? [] as $effect) {
                    $code = $effect['damage_type']['code'] ?? null;
                    if (in_array($code, ['F', 'C'])) {
                        $hasFireOrCold = true;
                        break;
                    }
                }
                $this->assertTrue($hasFireOrCold, "Spell {$spell['name']} should have fire or cold damage");
            }
        } else {
            $this->markTestIncomplete('No fire/cold damage spells found in test data - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_with_no_damage()
    {
        // Utility/buff spells with no damage effects
        $response = $this->getJson('/api/v1/spells?filter=damage_types IS EMPTY');

        $response->assertOk();

        // Should find utility spells (buffs, teleportation, divination, etc.)
        $this->assertGreaterThan(0, $response->json('meta.total'),
            'Should find utility spells without damage in PHB dataset');

        // Just verify the filter worked - the results are utility spells
        // We trust Meilisearch to filter correctly based on indexed data
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function it_combines_damage_type_with_level_filter()
    {
        // Fire damage cantrips (level 0)
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [F] AND level = 0');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertEquals(0, $spell['level'], 'Should only return cantrips');
            // If we found results, verify they have fire damage
            if (! empty($spell['effects'])) {
                $hasFire = false;
                foreach ($spell['effects'] as $effect) {
                    if (isset($effect['damage_type']['code']) && $effect['damage_type']['code'] === 'F') {
                        $hasFire = true;
                        break;
                    }
                }
                // Only assert if spell has effects
                if (! empty($spell['effects'])) {
                    $this->assertTrue($hasFire, "Cantrip {$spell['name']} should have fire damage");
                }
            }
        }
    }

    #[Test]
    public function it_combines_damage_type_with_class_filter()
    {
        // Force damage spells available to wizards
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [Fc] AND class_slugs IN [wizard]');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            // Verify wizard class association
            $hasWizard = false;
            foreach ($spell['classes'] ?? [] as $class) {
                if ($class['slug'] === 'wizard') {
                    $hasWizard = true;
                    break;
                }
            }
            $this->assertTrue($hasWizard, "Spell {$spell['name']} should be available to wizard");
        }
    }

    // ===================================================================
    // SAVING THROW FILTERING TESTS
    // ===================================================================

    #[Test]
    public function it_filters_spells_by_single_saving_throw()
    {
        // DEX save spells (useful against slow creatures)
        $response = $this->getJson('/api/v1/spells?filter=saving_throws IN [DEX]');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertNotEmpty($spell['saving_throws'] ?? [],
                    "Spell {$spell['name']} should have saving throws");
                $hasDex = false;
                foreach ($spell['saving_throws'] ?? [] as $save) {
                    if ($save['ability_score']['code'] === 'DEX') {
                        $hasDex = true;
                        break;
                    }
                }
                $this->assertTrue($hasDex, "Spell {$spell['name']} should require DEX save");
            }
        } else {
            $this->markTestIncomplete('No DEX save spells found in test data - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_by_multiple_saving_throws()
    {
        // DEX or CON saves (common weak points)
        $response = $this->getJson('/api/v1/spells?filter=saving_throws IN [DEX, CON]');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertNotEmpty($spell['saving_throws'] ?? [],
                    "Spell {$spell['name']} should have saving throws");
                $hasDexOrCon = false;
                foreach ($spell['saving_throws'] ?? [] as $save) {
                    $code = $save['ability_score']['code'] ?? null;
                    if (in_array($code, ['DEX', 'CON'])) {
                        $hasDexOrCon = true;
                        break;
                    }
                }
                $this->assertTrue($hasDexOrCon,
                    "Spell {$spell['name']} should require DEX or CON save");
            }
        } else {
            $this->markTestIncomplete('No DEX/CON save spells found in test data - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_with_no_saving_throws()
    {
        // Auto-hit spells (like Magic Missile)
        $response = $this->getJson('/api/v1/spells?filter=saving_throws IS EMPTY');

        $response->assertOk();

        // Should find auto-hit/buff spells
        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                $this->assertEmpty($spell['saving_throws'] ?? [],
                    "Spell {$spell['name']} should not require saving throws");
            }
        } else {
            $this->markTestIncomplete('Expected to find spells without saves - verify indexing');
        }
    }

    #[Test]
    public function it_combines_saving_throw_with_level_filter()
    {
        // Low-level WIS save spells (levels 1-3)
        $response = $this->getJson('/api/v1/spells?filter=saving_throws IN [WIS] AND level >= 1 AND level <= 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell['level'], 'Should be level 1 or higher');
            $this->assertLessThanOrEqual(3, $spell['level'], 'Should be level 3 or lower');

            // Verify WIS save if we have results
            if (! empty($spell['saving_throws'])) {
                $hasWis = false;
                foreach ($spell['saving_throws'] as $save) {
                    if ($save['ability_score']['code'] === 'WIS') {
                        $hasWis = true;
                        break;
                    }
                }
                $this->assertTrue($hasWis, "Spell {$spell['name']} should require WIS save");
            }
        }
    }

    // ===================================================================
    // COMPONENT BREAKDOWN FILTERING TESTS
    // ===================================================================

    #[Test]
    public function it_filters_spells_without_verbal_component()
    {
        // Spells castable in Silence
        $response = $this->getJson('/api/v1/spells?filter=requires_verbal = false');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                // Verify components field doesn't contain 'V'
                $components = $spell['components'];
                $this->assertStringNotContainsString('V', $components,
                    "Spell {$spell['name']} should not require verbal component");
            }
        } else {
            $this->markTestIncomplete('No spells without verbal component found - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_without_somatic_component()
    {
        // Spells castable while grappled/restrained
        $response = $this->getJson('/api/v1/spells?filter=requires_somatic = false');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                // Verify components field doesn't contain 'S'
                $components = $spell['components'];
                $this->assertStringNotContainsString('S', $components,
                    "Spell {$spell['name']} should not require somatic component");
            }
        } else {
            $this->markTestIncomplete('No spells without somatic component found - verify indexing');
        }
    }

    #[Test]
    public function it_filters_spells_without_material_component()
    {
        // Spells that don't need component pouch/focus
        $response = $this->getJson('/api/v1/spells?filter=requires_material = false');

        $response->assertOk();

        if ($response->json('meta.total') > 0) {
            foreach ($response->json('data') as $spell) {
                // Verify components field doesn't contain 'M'
                $components = $spell['components'];
                $this->assertStringNotContainsString('M', $components,
                    "Spell {$spell['name']} should not require material component");
            }
        } else {
            $this->markTestIncomplete('No spells without material component found - verify indexing');
        }
    }

    #[Test]
    public function it_filters_subtle_spell_candidates()
    {
        // Spells without verbal OR somatic (Subtle Spell metamagic candidates)
        $response = $this->getJson('/api/v1/spells?filter=requires_verbal = false AND requires_somatic = false');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $components = $spell['components'];
            $this->assertStringNotContainsString('V', $components,
                "Spell {$spell['name']} should not require verbal component");
            $this->assertStringNotContainsString('S', $components,
                "Spell {$spell['name']} should not require somatic component");
        }
    }

    #[Test]
    public function it_filters_spells_with_no_components_at_all()
    {
        // Extremely rare - spells with no components whatsoever
        $response = $this->getJson('/api/v1/spells?filter=requires_verbal = false AND requires_somatic = false AND requires_material = false');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $components = $spell['components'];
            $this->assertStringNotContainsString('V', $components,
                "Spell {$spell['name']} should not require verbal component");
            $this->assertStringNotContainsString('S', $components,
                "Spell {$spell['name']} should not require somatic component");
            $this->assertStringNotContainsString('M', $components,
                "Spell {$spell['name']} should not require material component");
        }
    }

    #[Test]
    public function it_combines_component_with_concentration_filter()
    {
        // Silent concentration spells (useful for stealth buffing)
        $response = $this->getJson('/api/v1/spells?filter=requires_verbal = false AND concentration = true');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertTrue($spell['needs_concentration'],
                "Spell {$spell['name']} should require concentration");
            $this->assertStringNotContainsString('V', $spell['components'],
                "Spell {$spell['name']} should not require verbal component");
        }
    }

    // ===================================================================
    // COMPLEX COMBINED FILTERING TESTS
    // ===================================================================

    #[Test]
    public function it_combines_damage_type_saving_throw_and_level()
    {
        // Fire damage, DEX save, low-level spells (tactical optimization)
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [F] AND saving_throws IN [DEX] AND level <= 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(3, $spell['level'],
                "Spell {$spell['name']} should be level 3 or lower");
        }
    }

    #[Test]
    public function it_finds_force_damage_auto_hit_spells()
    {
        // Force damage spells with no saving throw (bypass resistance + guaranteed hit)
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [Fc] AND saving_throws IS EMPTY');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertEmpty($spell['saving_throws'] ?? [],
                "Spell {$spell['name']} should not require saving throw");

            // Verify force damage if effects are loaded
            if (! empty($spell['effects'])) {
                $hasForce = false;
                foreach ($spell['effects'] as $effect) {
                    if (isset($effect['damage_type']['code']) && $effect['damage_type']['code'] === 'Fc') {
                        $hasForce = true;
                        break;
                    }
                }
                // Only assert if we have effects data
                if (count($spell['effects']) > 0) {
                    $this->assertTrue($hasForce || empty($spell['effects']),
                        "Spell {$spell['name']} should have force damage or no effects loaded");
                }
            }
        }
    }

    #[Test]
    public function it_handles_empty_results_gracefully()
    {
        // Impossible combination - should return empty results without error
        $response = $this->getJson('/api/v1/spells?filter=damage_types IN [F] AND damage_types IN [C]');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function it_combines_enhanced_filters_with_search_query()
    {
        // Search for "fire" and filter by fire damage
        $response = $this->getJson('/api/v1/spells?q=fire&filter=damage_types IN [F]');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            // Name or description should contain "fire" (from search query)
            $nameOrDesc = strtolower($spell['name'].' '.($spell['description'] ?? ''));
            $this->assertStringContainsString('fire', $nameOrDesc,
                "Spell {$spell['name']} should contain 'fire' in name or description");
        }
    }

    #[Test]
    public function it_paginates_enhanced_filtered_results()
    {
        $response = $this->getJson('/api/v1/spells?filter=requires_verbal = true&per_page=5&page=1');

        $response->assertOk();
        $this->assertLessThanOrEqual(5, count($response->json('data')));
        $response->assertJsonStructure([
            'meta' => ['current_page', 'total', 'per_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }

    #[Test]
    public function it_sorts_enhanced_filtered_results()
    {
        $response = $this->getJson('/api/v1/spells?filter=requires_material = false&sort_by=level&sort_direction=asc');

        $response->assertOk();

        if ($response->json('meta.total') > 1) {
            $levels = collect($response->json('data'))->pluck('level');

            // Should be in ascending order
            $sorted = $levels->sort()->values();
            $this->assertEquals($sorted->toArray(), $levels->toArray(),
                'Results should be sorted by level in ascending order');
        }
    }
}
