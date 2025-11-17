<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_has_fillable_attributes(): void
    {
        $spell = new Spell([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'duration' => 'Instantaneous',
            'description' => 'A bright streak...',
        ]);

        $this->assertEquals('Fireball', $spell->name);
        $this->assertEquals(3, $spell->level);
    }

    public function test_spell_belongs_to_school(): void
    {
        $spell = Spell::factory()->create();
        $this->assertInstanceOf(SpellSchool::class, $spell->school);
    }

    public function test_spell_belongs_to_source_book(): void
    {
        $spell = Spell::factory()->create();
        $this->assertInstanceOf(SourceBook::class, $spell->sourceBook);
    }

    public function test_spell_slug_is_generated_from_name(): void
    {
        $spell = new Spell(['name' => 'Acid Splash']);
        $spell->generateSlug();
        $this->assertEquals('acid-splash', $spell->slug);
    }

    public function test_spell_slug_auto_generates_on_create(): void
    {
        $spell = Spell::factory()->create(['name' => 'Magic Missile']);
        $this->assertEquals('magic-missile', $spell->slug);
    }
}
