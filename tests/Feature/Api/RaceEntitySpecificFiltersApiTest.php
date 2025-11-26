<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use Tests\TestCase;

/**
 * Tests for Race-specific filter operators using Meilisearch.
 *
 * These tests use pre-imported data from SearchTestExtension.
 * No RefreshDatabase needed - all tests are read-only against shared data.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
#[\PHPUnit\Framework\Attributes\Group('search-isolated')]
class RaceEntitySpecificFiltersApiTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_ability_bonus_int(): void
    {
        // Get count of races with INT bonuses from pre-imported data
        $intBonusCount = Race::whereHas('modifiers', function ($query) {
            $query->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'INT');
                })
                ->where('value', '>', 0);
        })->count();

        $this->assertGreaterThan(0, $intBonusCount, 'Should have races with INT bonus (e.g., High Elf, Gnome)');

        // Act: Filter by ability_int_bonus > 0 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_int_bonus > 0');

        // Assert: Only races with INT bonus returned
        $response->assertOk();
        $this->assertEquals($intBonusCount, $response->json('meta.total'));

        // Verify all returned races have INT bonuses
        foreach ($response->json('data') as $race) {
            $raceModel = Race::find($race['id']);
            $hasIntBonus = $raceModel->modifiers()
                ->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'INT');
                })
                ->where('value', '>', 0)
                ->exists();
            $this->assertTrue($hasIntBonus, "{$race['name']} should have INT bonus");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_ability_bonus_str(): void
    {
        // Get count of races with STR bonuses from pre-imported data
        $strBonusCount = Race::whereHas('modifiers', function ($query) {
            $query->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'STR');
                })
                ->where('value', '>', 0);
        })->count();

        $this->assertGreaterThan(0, $strBonusCount, 'Should have races with STR bonus (e.g., Mountain Dwarf, Dragonborn)');

        // Act: Filter by ability_str_bonus > 0 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_str_bonus > 0');

        // Assert: Only races with STR bonus returned
        $response->assertOk();
        $this->assertEquals($strBonusCount, $response->json('meta.total'));

        // Verify all returned races have STR bonuses
        foreach ($response->json('data') as $race) {
            $raceModel = Race::find($race['id']);
            $hasStrBonus = $raceModel->modifiers()
                ->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'STR');
                })
                ->where('value', '>', 0)
                ->exists();
            $this->assertTrue($hasStrBonus, "{$race['name']} should have STR bonus");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_size_small(): void
    {
        // Get count of small races from pre-imported data
        $smallRaceCount = Race::whereHas('size', function ($query) {
            $query->where('code', 'S');
        })->count();

        $this->assertGreaterThan(0, $smallRaceCount, 'Should have small races (e.g., Halfling, Gnome)');

        // Act: Filter by size_code = S using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=size_code = S');

        // Assert: Only small races returned
        $response->assertOk();
        $this->assertEquals($smallRaceCount, $response->json('meta.total'));

        // Verify all returned races are small
        foreach ($response->json('data') as $race) {
            $this->assertEquals('S', $race['size']['code'], "{$race['name']} should be size S");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_size_medium(): void
    {
        // Get count of medium races from pre-imported data
        $mediumRaceCount = Race::whereHas('size', function ($query) {
            $query->where('code', 'M');
        })->count();

        $this->assertGreaterThan(0, $mediumRaceCount, 'Should have medium races (e.g., Human, Elf)');

        // Act: Filter by size_code = M using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=size_code = M');

        // Assert: Only medium races returned
        $response->assertOk();
        $this->assertEquals($mediumRaceCount, $response->json('meta.total'));

        // Verify all returned races are medium
        foreach ($response->json('data') as $race) {
            $this->assertEquals('M', $race['size']['code'], "{$race['name']} should be size M");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_min_speed_35(): void
    {
        // Get count of races with speed >= 35 from pre-imported data
        $fastRaceCount = Race::where('speed', '>=', 35)->count();

        $this->assertGreaterThan(0, $fastRaceCount, 'Should have races with speed >= 35 (e.g., Wood Elf)');

        // Act: Filter by speed >= 35 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=speed >= 35');

        // Assert: Only races with speed >= 35 returned
        $response->assertOk();
        $this->assertEquals($fastRaceCount, $response->json('meta.total'));

        // Verify all returned races have speed >= 35
        foreach ($response->json('data') as $race) {
            $this->assertGreaterThanOrEqual(35, $race['speed'], "{$race['name']} should have speed >= 35");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_has_darkvision_true(): void
    {
        // Get count of races with darkvision tag from pre-imported data
        $darkvisionRaceCount = Race::withAnyTags(['darkvision'])->count();

        $this->assertGreaterThan(0, $darkvisionRaceCount, 'Should have races with darkvision (e.g., Dwarf, Elf, Tiefling)');

        // Act: Filter by tag_slugs IN [darkvision] using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=tag_slugs IN [darkvision]');

        // Assert: Only races with darkvision returned
        $response->assertOk();
        $this->assertEquals($darkvisionRaceCount, $response->json('meta.total'));

        // Verify all returned races have darkvision tag
        foreach ($response->json('data') as $race) {
            $tagSlugs = collect($race['tags'])->pluck('slug')->toArray();
            $this->assertContains('darkvision', $tagSlugs, "{$race['name']} should have darkvision tag");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_combined_ability_bonus_and_has_darkvision(): void
    {
        // Get count of races with both INT bonus and darkvision from pre-imported data
        $combinedCount = Race::whereHas('modifiers', function ($query) {
            $query->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'INT');
                })
                ->where('value', '>', 0);
        })->withAnyTags(['darkvision'])->count();

        $this->assertGreaterThan(0, $combinedCount, 'Should have races with INT bonus AND darkvision (e.g., Gnome, High Elf)');

        // Act: Filter by ability_int_bonus > 0 AND tag_slugs IN [darkvision] using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_int_bonus > 0 AND tag_slugs IN [darkvision]');

        // Assert: Only races with both INT bonus and darkvision returned
        $response->assertOk();
        $this->assertEquals($combinedCount, $response->json('meta.total'));

        // Verify all returned races have both INT bonus and darkvision
        foreach ($response->json('data') as $race) {
            $raceModel = Race::find($race['id']);

            // Check INT bonus
            $hasIntBonus = $raceModel->modifiers()
                ->where('modifier_category', 'ability_score')
                ->whereHas('abilityScore', function ($q) {
                    $q->where('code', 'INT');
                })
                ->where('value', '>', 0)
                ->exists();
            $this->assertTrue($hasIntBonus, "{$race['name']} should have INT bonus");

            // Check darkvision tag
            $tagSlugs = collect($race['tags'])->pluck('slug')->toArray();
            $this->assertContains('darkvision', $tagSlugs, "{$race['name']} should have darkvision tag");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_filter_parameter_with_invalid_syntax(): void
    {
        // Act: Send invalid filter syntax (this will be caught by Meilisearch, not validation)
        // In Meilisearch-first architecture, invalid filter syntax returns 422 from service layer
        $response = $this->getJson('/api/v1/races?filter=invalid syntax here!!!');

        // Assert: Either validation error or Meilisearch error (both return 422)
        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_size_filter(): void
    {
        // Act: Send valid size filter (uses pre-imported data)
        $response = $this->getJson('/api/v1/races?filter=size_code = S');

        // Assert: Success
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_speed_filter(): void
    {
        // Act: Send valid speed filter (uses pre-imported data)
        $response = $this->getJson('/api/v1/races?filter=speed >= 30');

        // Assert: Success
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_darkvision_filter(): void
    {
        // Act: Send valid darkvision filter (uses pre-imported data)
        $response = $this->getJson('/api/v1/races?filter=tag_slugs IN [darkvision]');

        // Assert: Success
        $response->assertOk();
    }
}
