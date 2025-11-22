<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonsterApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_get_all_monsters()
    {
        Monster::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/monsters');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'slug',
                    'name',
                    'type',
                    'alignment',
                    'armor_class',
                    'hit_points_average',
                    'challenge_rating',
                ],
            ],
        ]);
    }

    #[Test]
    public function can_get_single_monster_by_id()
    {
        $monster = Monster::factory()->create(['name' => 'Ancient Red Dragon']);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Ancient Red Dragon');
    }

    #[Test]
    public function can_get_single_monster_by_slug()
    {
        $monster = Monster::factory()->create([
            'name' => 'Ancient Red Dragon',
            'slug' => 'ancient-red-dragon',
        ]);

        $response = $this->getJson('/api/v1/monsters/ancient-red-dragon');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Ancient Red Dragon');
    }

    #[Test]
    public function can_search_monsters_by_name()
    {
        Monster::factory()->create(['name' => 'Red Dragon']);
        Monster::factory()->create(['name' => 'Blue Dragon']);
        Monster::factory()->create(['name' => 'Goblin']);

        $response = $this->getJson('/api/v1/monsters?q=Dragon');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function can_filter_monsters_by_challenge_rating()
    {
        Monster::factory()->create(['challenge_rating' => '1']);
        Monster::factory()->create(['challenge_rating' => '5']);
        Monster::factory()->create(['challenge_rating' => '10']);

        $response = $this->getJson('/api/v1/monsters?challenge_rating=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.challenge_rating', '5');
    }

    #[Test]
    public function can_filter_monsters_by_cr_range()
    {
        Monster::factory()->create(['challenge_rating' => '1']);
        Monster::factory()->create(['challenge_rating' => '5']);
        Monster::factory()->create(['challenge_rating' => '10']);
        Monster::factory()->create(['challenge_rating' => '15']);

        $response = $this->getJson('/api/v1/monsters?min_cr=5&max_cr=10');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function can_filter_monsters_by_type()
    {
        Monster::factory()->create(['type' => 'dragon']);
        Monster::factory()->create(['type' => 'humanoid']);
        Monster::factory()->create(['type' => 'undead']);

        $response = $this->getJson('/api/v1/monsters?type=dragon');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'dragon');
    }

    #[Test]
    public function can_filter_monsters_by_size()
    {
        $small = Size::where('code', 'S')->first();
        $medium = Size::where('code', 'M')->first();
        $large = Size::where('code', 'L')->first();

        Monster::factory()->create(['size_id' => $small->id]);
        Monster::factory()->create(['size_id' => $medium->id]);
        Monster::factory()->create(['size_id' => $large->id]);

        $response = $this->getJson('/api/v1/monsters?size=L');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function can_filter_monsters_by_alignment()
    {
        Monster::factory()->create(['alignment' => 'lawful good']);
        Monster::factory()->create(['alignment' => 'chaotic evil']);
        Monster::factory()->create(['alignment' => 'neutral']);

        $response = $this->getJson('/api/v1/monsters?alignment=evil');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function monster_includes_size_in_response()
    {
        $monster = Monster::factory()->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'size' => ['id', 'code', 'name'],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_traits_in_response()
    {
        $monster = Monster::factory()
            ->hasTraits(2)
            ->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'traits' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_actions_in_response()
    {
        $monster = Monster::factory()
            ->hasActions(3)
            ->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'actions' => [
                    '*' => ['id', 'name', 'description', 'action_type'],
                ],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_legendary_actions_in_response()
    {
        $monster = Monster::factory()
            ->hasLegendaryActions(3)
            ->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'legendary_actions' => [
                    '*' => ['id', 'name', 'description', 'action_cost'],
                ],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_spellcasting_in_response()
    {
        $monster = Monster::factory()
            ->hasSpellcasting()
            ->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'spellcasting' => [
                    'id',
                    'description',
                    'spellcasting_ability',
                ],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_modifiers_in_response()
    {
        $monster = Monster::factory()
            ->hasModifiers(2)
            ->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'modifiers' => [
                    '*' => ['id', 'modifier_category', 'value'],
                ],
            ],
        ]);
    }

    #[Test]
    public function can_paginate_monsters()
    {
        Monster::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/monsters?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function can_sort_monsters_by_name()
    {
        Monster::factory()->create(['name' => 'Zombie']);
        Monster::factory()->create(['name' => 'Aarakocra']);

        $response = $this->getJson('/api/v1/monsters?sort_by=name&sort_direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Aarakocra');
    }

    #[Test]
    public function can_sort_monsters_by_challenge_rating()
    {
        Monster::factory()->create(['name' => 'Weak Monster', 'challenge_rating' => '1']);
        Monster::factory()->create(['name' => 'Strong Monster', 'challenge_rating' => '10']);

        $response = $this->getJson('/api/v1/monsters?sort_by=challenge_rating&sort_direction=desc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Strong Monster');
    }

    #[Test]
    public function returns_404_for_nonexistent_monster()
    {
        $response = $this->getJson('/api/v1/monsters/999999');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_nonexistent_monster_slug()
    {
        $response = $this->getJson('/api/v1/monsters/nonexistent-monster');

        $response->assertNotFound();
    }

    #[Test]
    public function can_filter_monsters_by_spell()
    {
        $source = $this->getSource('PHB');
        $school = \App\Models\SpellSchool::factory()->create(['code' => 'EVO']);

        // Create spells
        $fireball = \App\Models\Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        $lightning = \App\Models\Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'spell_school_id' => $school->id,
        ]);

        // Create monsters
        $lich = Monster::factory()->create(['name' => 'Lich']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $lich->id,
            'source_id' => $source->id,
            'pages' => '202',
        ]);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id]);

        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $archmage->id,
            'source_id' => $source->id,
            'pages' => '342',
        ]);
        $archmage->entitySpells()->attach([$fireball->id]);

        $goblin = Monster::factory()->create(['name' => 'Goblin']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $goblin->id,
            'source_id' => $source->id,
            'pages' => '166',
        ]);
        // Goblin has no spells

        // Filter by Fireball - should return Lich and Archmage
        $response = $this->getJson('/api/v1/monsters?spells=fireball');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Lich', $names);
        $this->assertContains('Archmage', $names);
        $this->assertNotContains('Goblin', $names);
    }

    #[Test]
    public function can_filter_monsters_by_multiple_spells()
    {
        $source = $this->getSource('PHB');
        $school = \App\Models\SpellSchool::factory()->create(['code' => 'EVO']);

        // Create spells
        $fireball = \App\Models\Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        $lightning = \App\Models\Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'spell_school_id' => $school->id,
        ]);

        // Create monsters
        $lich = Monster::factory()->create(['name' => 'Lich']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $lich->id,
            'source_id' => $source->id,
            'pages' => '202',
        ]);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id]);

        $archmage = Monster::factory()->create(['name' => 'Archmage']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $archmage->id,
            'source_id' => $source->id,
            'pages' => '342',
        ]);
        $archmage->entitySpells()->attach([$fireball->id]); // Only Fireball

        // Filter by both spells - should only return Lich
        $response = $this->getJson('/api/v1/monsters?spells=fireball,lightning-bolt');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Lich');
    }

    #[Test]
    public function can_get_monster_spell_list()
    {
        $source = $this->getSource('PHB');
        $school = \App\Models\SpellSchool::factory()->create(['code' => 'EVO']);

        // Create spells
        $fireball = \App\Models\Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
        ]);

        $lightning = \App\Models\Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'level' => 3,
            'spell_school_id' => $school->id,
        ]);

        $mageHand = \App\Models\Spell::factory()->create([
            'name' => 'Mage Hand',
            'slug' => 'mage-hand',
            'level' => 0,
            'spell_school_id' => $school->id,
        ]);

        // Create monster with spells
        $lich = Monster::factory()->create(['name' => 'Lich']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $lich->id,
            'source_id' => $source->id,
            'pages' => '202',
        ]);
        $lich->entitySpells()->attach([$fireball->id, $lightning->id, $mageHand->id]);

        // Get monster's spell list
        $response = $this->getJson("/api/v1/monsters/{$lich->id}/spells");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'level',
                    'school' => ['id', 'name', 'code'],
                ],
            ],
        ]);

        // Verify spell names
        $spellNames = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Fireball', $spellNames);
        $this->assertContains('Lightning Bolt', $spellNames);
        $this->assertContains('Mage Hand', $spellNames);
    }

    #[Test]
    public function monster_spell_list_returns_empty_for_non_spellcaster()
    {
        $source = $this->getSource('PHB');

        $goblin = Monster::factory()->create(['name' => 'Goblin']);
        \App\Models\EntitySource::create([
            'reference_type' => Monster::class,
            'reference_id' => $goblin->id,
            'source_id' => $source->id,
            'pages' => '166',
        ]);

        $response = $this->getJson("/api/v1/monsters/{$goblin->id}/spells");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function monster_spell_list_returns_404_for_nonexistent_monster()
    {
        $response = $this->getJson('/api/v1/monsters/999999/spells');

        $response->assertNotFound();
    }

    /**
     * Override getSource to create source if it doesn't exist
     */
    protected function getSource(string $code): \App\Models\Source
    {
        return \App\Models\Source::where('code', $code)->first()
            ?? \App\Models\Source::factory()->create(['code' => $code, 'name' => "Test Source {$code}"]);
    }
}
