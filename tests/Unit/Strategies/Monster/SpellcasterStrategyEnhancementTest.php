<?php

namespace Tests\Unit\Strategies\Monster;

use App\Models\Monster;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Services\Importers\Strategies\Monster\SpellcasterStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellcasterStrategyEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private SpellcasterStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SpellcasterStrategy;
    }

    #[Test]
    public function it_syncs_single_spell_to_entity_spells(): void
    {
        // Arrange: Create spell in database
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        // Create monster
        $monster = Monster::factory()->create(['name' => 'Lich']);

        // Monster data with spell
        $monsterData = [
            'spells' => 'Fireball',
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: Spell synced to entity_spells
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());

        // Assert: Metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(1, $metadata['spells_matched'] ?? 0);
    }

    #[Test]
    public function it_syncs_multiple_spells_to_entity_spells(): void
    {
        // Arrange: Create spells in database
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);
        $lightning = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'spell_school_id' => $school->id,
        ]);
        $cone = Spell::factory()->create([
            'name' => 'Cone of Cold',
            'slug' => 'cone-of-cold',
            'spell_school_id' => $school->id,
        ]);

        // Create monster
        $monster = Monster::factory()->create(['name' => 'Archmage']);

        // Monster data with multiple spells
        $monsterData = [
            'spells' => 'Fireball, Lightning Bolt, Cone of Cold',
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: All spells synced
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());
        $this->assertTrue($monster->entitySpells()->where('spell_id', $lightning->id)->exists());
        $this->assertTrue($monster->entitySpells()->where('spell_id', $cone->id)->exists());
        $this->assertEquals(3, $monster->entitySpells()->count());

        // Assert: Metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(3, $metadata['spells_matched'] ?? 0);
    }

    #[Test]
    public function it_handles_case_insensitive_spell_matching(): void
    {
        // Arrange: Create spell with proper title case
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        // Create monster with lowercase spell name
        $monster = Monster::factory()->create(['name' => 'Wizard']);

        $monsterData = [
            'spells' => 'fireball',  // lowercase
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: Spell matched despite case difference
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());

        // Assert: Metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(1, $metadata['spells_matched'] ?? 0);
    }

    #[Test]
    public function it_logs_warning_for_spell_not_found(): void
    {
        // Arrange: Create monster with nonexistent spell
        $monster = Monster::factory()->create(['name' => 'Sorcerer']);

        $monsterData = [
            'spells' => 'Nonexistent Spell',
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: No spell synced
        $this->assertEquals(0, $monster->entitySpells()->count());

        // Assert: Warning logged in metadata
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(1, $metadata['spells_not_found'] ?? 0);
        $this->assertEquals(0, $metadata['spells_matched'] ?? 0);
    }

    #[Test]
    public function it_handles_mixed_found_and_not_found_spells(): void
    {
        // Arrange: Create some spells (not all)
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);
        $lightning = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'spell_school_id' => $school->id,
        ]);

        // Create monster with mix of existing and nonexistent spells
        $monster = Monster::factory()->create(['name' => 'Lich']);

        $monsterData = [
            'spells' => 'Fireball, Nonexistent Spell, Lightning Bolt',
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: Only found spells synced
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());
        $this->assertTrue($monster->entitySpells()->where('spell_id', $lightning->id)->exists());
        $this->assertEquals(2, $monster->entitySpells()->count());

        // Assert: Metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(2, $metadata['spells_matched'] ?? 0);
        $this->assertEquals(1, $metadata['spells_not_found'] ?? 0);
    }

    #[Test]
    public function it_trims_whitespace_from_spell_names(): void
    {
        // Arrange: Create spells
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);
        $lightning = Spell::factory()->create([
            'name' => 'Lightning Bolt',
            'slug' => 'lightning-bolt',
            'spell_school_id' => $school->id,
        ]);

        // Create monster with extra whitespace
        $monster = Monster::factory()->create(['name' => 'Mage']);

        $monsterData = [
            'spells' => ' Fireball ,  Lightning Bolt  ',  // Extra whitespace
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: Spells matched despite whitespace
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());
        $this->assertTrue($monster->entitySpells()->where('spell_id', $lightning->id)->exists());
        $this->assertEquals(2, $monster->entitySpells()->count());
    }

    #[Test]
    public function it_handles_empty_spell_list_gracefully(): void
    {
        // Arrange: Create monster with empty spell string
        $monster = Monster::factory()->create(['name' => 'Warrior']);

        $monsterData = [
            'spells' => '',
        ];

        // Act: Call strategy (should not error)
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: No spells synced
        $this->assertEquals(0, $monster->entitySpells()->count());

        // Assert: No metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(0, $metadata['spells_matched'] ?? 0);
        $this->assertEquals(0, $metadata['spells_not_found'] ?? 0);
    }

    #[Test]
    public function it_uses_existing_spell_cache_for_performance(): void
    {
        // Arrange: Create spell
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        // Create two monsters with the same spell
        $monster1 = Monster::factory()->create(['name' => 'Lich']);
        $monster2 = Monster::factory()->create(['name' => 'Archmage']);

        $monsterData = [
            'spells' => 'Fireball',
        ];

        // Act: Call strategy twice (should use cache on second call)
        $this->strategy->afterCreate($monster1, $monsterData);
        $this->strategy->afterCreate($monster2, $monsterData);

        // Assert: Both monsters have the spell
        $this->assertTrue($monster1->entitySpells()->where('spell_id', $fireball->id)->exists());
        $this->assertTrue($monster2->entitySpells()->where('spell_id', $fireball->id)->exists());

        // Note: Performance verification would require query counting,
        // but this test verifies functional behavior with cache
    }
}
