<?php

namespace Tests\Unit\Concerns;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Services\Importers\Concerns\ImportsClassAssociations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ImportsClassAssociationsTest extends TestCase
{
    use RefreshDatabase;

    private TestImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new TestImporter;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_exact_match(): void
    {
        $fighter = CharacterClass::firstOrCreate(['slug' => 'fighter'], ['name' => 'Fighter']);
        $eldritchKnight = CharacterClass::firstOrCreate(
            ['slug' => 'eldritch-knight'],
            ['name' => 'Eldritch Knight', 'parent_class_id' => $fighter->id, 'hit_die' => 0, 'description' => 'Test subclass']
        );

        $spell = Spell::factory()->create();

        $this->importer->syncClassAssociations($spell, ['Fighter (Eldritch Knight)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($eldritchKnight->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_fuzzy_match(): void
    {
        $warlock = CharacterClass::firstOrCreate(['slug' => 'warlock'], ['name' => 'Warlock']);
        $archfey = CharacterClass::firstOrCreate(
            ['slug' => 'the-archfey'],
            ['name' => 'The Archfey', 'parent_class_id' => $warlock->id, 'hit_die' => 0, 'description' => 'Test subclass']
        );

        $spell = Spell::factory()->create();

        // XML has "Archfey" (without "The")
        $this->importer->syncClassAssociations($spell, ['Warlock (Archfey)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($archfey->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_alias_mapping(): void
    {
        $druid = CharacterClass::firstOrCreate(['slug' => 'druid'], ['name' => 'Druid']);
        $circleOfLand = CharacterClass::firstOrCreate(
            ['slug' => 'circle-of-the-land'],
            ['name' => 'Circle of the Land', 'parent_class_id' => $druid->id, 'hit_die' => 0, 'description' => 'Test subclass']
        );

        $spell = Spell::factory()->create();

        // XML has "Coast" which should map to "Circle of the Land"
        $this->importer->syncClassAssociations($spell, ['Druid (Coast)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($circleOfLand->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_base_class_only(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);

        // Create a subclass with same name pattern (should NOT be matched)
        $fighter = CharacterClass::firstOrCreate(['slug' => 'fighter'], ['name' => 'Fighter']);
        CharacterClass::firstOrCreate(
            ['slug' => 'wizard-subclass'],
            ['name' => 'Wizard Subclass', 'parent_class_id' => $fighter->id, 'hit_die' => 0, 'description' => 'Test subclass']
        );

        $spell = Spell::factory()->create();

        // Should match base class only (no parentheses)
        $this->importer->syncClassAssociations($spell, ['Wizard']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($wizard->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_multiple_base_classes(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);
        $sorcerer = CharacterClass::firstOrCreate(['slug' => 'sorcerer'], ['name' => 'Sorcerer']);

        $spell = Spell::factory()->create();

        $this->importer->syncClassAssociations($spell, ['Wizard', 'Sorcerer']);

        $this->assertEquals(2, $spell->classes()->count());
        $classIds = $spell->classes()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([$wizard->id, $sorcerer->id], $classIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sync_replaces_existing_associations(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);
        $sorcerer = CharacterClass::firstOrCreate(['slug' => 'sorcerer'], ['name' => 'Sorcerer']);
        $warlock = CharacterClass::firstOrCreate(['slug' => 'warlock'], ['name' => 'Warlock']);

        $spell = Spell::factory()->create();

        // Initial association
        $spell->classes()->attach($wizard->id);
        $this->assertEquals(1, $spell->classes()->count());

        // Sync with different classes (should REPLACE)
        $this->importer->syncClassAssociations($spell, ['Sorcerer', 'Warlock']);

        $this->assertEquals(2, $spell->classes()->count());
        $classIds = $spell->classes()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([$sorcerer->id, $warlock->id], $classIds);
        $this->assertNotContains($wizard->id, $classIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function add_merges_with_existing_associations(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);
        $sorcerer = CharacterClass::firstOrCreate(['slug' => 'sorcerer'], ['name' => 'Sorcerer']);
        $warlock = CharacterClass::firstOrCreate(['slug' => 'warlock'], ['name' => 'Warlock']);

        $spell = Spell::factory()->create();

        // Initial association
        $spell->classes()->attach($wizard->id);
        $this->assertEquals(1, $spell->classes()->count());

        // Add new classes (should MERGE)
        $count = $this->importer->addClassAssociations($spell, ['Sorcerer', 'Warlock']);

        $this->assertEquals(2, $count, 'Should return count of new associations');
        $this->assertEquals(3, $spell->classes()->count());
        $classIds = $spell->classes()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([$wizard->id, $sorcerer->id, $warlock->id], $classIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function add_handles_duplicate_classes_correctly(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);
        $sorcerer = CharacterClass::firstOrCreate(['slug' => 'sorcerer'], ['name' => 'Sorcerer']);

        $spell = Spell::factory()->create();

        // Initial associations
        $spell->classes()->attach([$wizard->id, $sorcerer->id]);
        $this->assertEquals(2, $spell->classes()->count());

        // Add class that already exists (should not create duplicate)
        $count = $this->importer->addClassAssociations($spell, ['Wizard', 'Sorcerer']);

        $this->assertEquals(0, $count, 'Should return 0 for no new associations');
        $this->assertEquals(2, $spell->classes()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_unresolved_classes(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);

        $spell = Spell::factory()->create();

        // Mix of valid and invalid class names
        $this->importer->syncClassAssociations($spell, ['Wizard', 'FakeClass', 'Fighter (Nonexistent)']);

        // Should only associate valid class
        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($wizard->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_class_array(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);

        $spell = Spell::factory()->create();
        $spell->classes()->attach($wizard->id);

        // Sync with empty array should clear all associations
        $this->importer->syncClassAssociations($spell, []);

        $this->assertEquals(0, $spell->classes()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_mixed_base_and_subclass_names(): void
    {
        $wizard = CharacterClass::firstOrCreate(['slug' => 'wizard'], ['name' => 'Wizard']);
        $fighter = CharacterClass::firstOrCreate(['slug' => 'fighter'], ['name' => 'Fighter']);
        $eldritchKnight = CharacterClass::firstOrCreate(
            ['slug' => 'eldritch-knight'],
            ['name' => 'Eldritch Knight', 'parent_class_id' => $fighter->id, 'hit_die' => 0, 'description' => 'Test subclass']
        );

        $spell = Spell::factory()->create();

        // Mix of base class and subclass
        $this->importer->syncClassAssociations($spell, ['Wizard', 'Fighter (Eldritch Knight)']);

        $this->assertEquals(2, $spell->classes()->count());
        $classIds = $spell->classes()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([$wizard->id, $eldritchKnight->id], $classIds);
    }
}

// Test helper class that uses the trait
class TestImporter
{
    use ImportsClassAssociations;
}
