<?php

namespace Tests\Unit\Concerns;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Services\Importers\Concerns\ImportsClassAssociations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        $eldritchKnight = CharacterClass::factory()->create([
            'name' => 'Eldritch Knight',
            'slug' => 'eldritch-knight',
            'parent_class_id' => $fighter->id,
        ]);

        $spell = Spell::factory()->create();

        $this->importer->syncClassAssociations($spell, ['Fighter (Eldritch Knight)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($eldritchKnight->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_fuzzy_match(): void
    {
        $warlock = CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);
        $archfey = CharacterClass::factory()->create([
            'name' => 'The Archfey',  // Database has "The Archfey"
            'slug' => 'the-archfey',
            'parent_class_id' => $warlock->id,
        ]);

        $spell = Spell::factory()->create();

        // XML has "Archfey" (without "The")
        $this->importer->syncClassAssociations($spell, ['Warlock (Archfey)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($archfey->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_alias_mapping(): void
    {
        $druid = CharacterClass::factory()->create(['name' => 'Druid', 'slug' => 'druid']);
        $circleOfLand = CharacterClass::factory()->create([
            'name' => 'Circle of the Land',
            'slug' => 'circle-of-the-land',
            'parent_class_id' => $druid->id,
        ]);

        $spell = Spell::factory()->create();

        // XML has "Coast" which should map to "Circle of the Land"
        $this->importer->syncClassAssociations($spell, ['Druid (Coast)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($circleOfLand->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_base_class_only(): void
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        // Create a subclass with same name pattern (should NOT be matched)
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        CharacterClass::factory()->create([
            'name' => 'Wizard Subclass',
            'slug' => 'wizard-subclass',
            'parent_class_id' => $fighter->id,
        ]);

        $spell = Spell::factory()->create();

        // Should match base class only (no parentheses)
        $this->importer->syncClassAssociations($spell, ['Wizard']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($wizard->id, $spell->classes()->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_multiple_base_classes(): void
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $sorcerer = CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

        $spell = Spell::factory()->create();

        $this->importer->syncClassAssociations($spell, ['Wizard', 'Sorcerer']);

        $this->assertEquals(2, $spell->classes()->count());
        $classIds = $spell->classes()->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([$wizard->id, $sorcerer->id], $classIds);
    }
}

// Test helper class that uses the trait
class TestImporter
{
    use ImportsClassAssociations;
}
