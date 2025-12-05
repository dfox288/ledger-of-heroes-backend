<?php

namespace Tests\Unit\Concerns;

use App\Models\Spell;
use App\Services\Concerns\NormalizesSpellNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class NormalizesSpellNamesTest extends TestCase
{
    use RefreshDatabase;

    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class using the trait
        $this->subject = new class
        {
            use NormalizesSpellNames;

            // Expose protected methods for testing
            public function test_normalize_spell_name(string $name): string
            {
                return $this->normalizeSpellName($name);
            }

            public function test_find_spell(string $name): ?Spell
            {
                return $this->findSpell($name);
            }

            public function test_clear_spell_cache(): void
            {
                $this->clearSpellCache();
            }

            public function getCacheSize(): int
            {
                return count($this->spellCache);
            }
        };
    }

    #[Test]
    public function it_normalizes_spell_names_to_title_case(): void
    {
        $this->assertEquals('Cure Wounds', $this->subject->test_normalize_spell_name('cure wounds'));
        $this->assertEquals('Fireball', $this->subject->test_normalize_spell_name('FIREBALL'));
        $this->assertEquals('Magic Missile', $this->subject->test_normalize_spell_name('magic missile'));
        $this->assertEquals('Melf\'s Acid Arrow', $this->subject->test_normalize_spell_name('melf\'s acid arrow'));
    }

    #[Test]
    public function it_trims_whitespace_from_spell_names(): void
    {
        $this->assertEquals('Fireball', $this->subject->test_normalize_spell_name('  fireball  '));
        $this->assertEquals('Cure Wounds', $this->subject->test_normalize_spell_name("\tcure wounds\n"));
    }

    #[Test]
    public function it_finds_spells_case_insensitively(): void
    {
        $spell = Spell::factory()->create(['name' => 'Fireball']);

        $found = $this->subject->test_find_spell('fireball');
        $this->assertNotNull($found);
        $this->assertEquals($spell->id, $found->id);

        $foundUpper = $this->subject->test_find_spell('FIREBALL');
        $this->assertNotNull($foundUpper);
        $this->assertEquals($spell->id, $foundUpper->id);
    }

    #[Test]
    public function it_returns_null_for_unknown_spells(): void
    {
        $found = $this->subject->test_find_spell('nonexistent spell');
        $this->assertNull($found);
    }

    #[Test]
    public function it_caches_spell_lookups(): void
    {
        Spell::factory()->create(['name' => 'Magic Missile']);

        $this->assertEquals(0, $this->subject->getCacheSize());

        // First lookup populates cache
        $this->subject->test_find_spell('magic missile');
        $this->assertEquals(1, $this->subject->getCacheSize());

        // Second lookup uses cache (size stays 1)
        $this->subject->test_find_spell('magic missile');
        $this->assertEquals(1, $this->subject->getCacheSize());

        // Different spell adds to cache
        $this->subject->test_find_spell('fireball');
        $this->assertEquals(2, $this->subject->getCacheSize());
    }

    #[Test]
    public function it_can_clear_the_cache(): void
    {
        Spell::factory()->create(['name' => 'Shield']);

        $this->subject->test_find_spell('shield');
        $this->assertEquals(1, $this->subject->getCacheSize());

        $this->subject->test_clear_spell_cache();
        $this->assertEquals(0, $this->subject->getCacheSize());
    }
}
