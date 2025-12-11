<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for route model binding supporting id and slug formats.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RouteBindingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_get_spell_by_slug(): void
    {
        $school = SpellSchool::first() ?? SpellSchool::factory()->create();
        $spell = Spell::factory()->create([
            'slug' => 'phb:fireball',
            'spell_school_id' => $school->id,
        ]);

        $response = $this->getJson('/api/v1/spells/phb:fireball');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:fireball');
    }

    #[Test]
    public function can_get_monster_by_slug(): void
    {
        $monster = Monster::factory()->create([
            'slug' => 'mm:aboleth',
        ]);

        $response = $this->getJson('/api/v1/monsters/mm:aboleth');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'mm:aboleth');
    }

    #[Test]
    public function can_get_race_by_slug(): void
    {
        $race = Race::factory()->create([
            'slug' => 'phb:elf',
        ]);

        $response = $this->getJson('/api/v1/races/phb:elf');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:elf');
    }

    #[Test]
    public function can_get_class_by_slug(): void
    {
        $class = CharacterClass::factory()->create([
            'slug' => 'phb:fighter',
        ]);

        $response = $this->getJson('/api/v1/classes/phb:fighter');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:fighter');
    }

    #[Test]
    public function can_get_item_by_slug(): void
    {
        $item = Item::factory()->create([
            'slug' => 'phb:longsword',
        ]);

        $response = $this->getJson('/api/v1/items/phb:longsword');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:longsword');
    }

    #[Test]
    public function can_get_feat_by_slug(): void
    {
        $feat = Feat::factory()->create([
            'slug' => 'phb:alert',
        ]);

        $response = $this->getJson('/api/v1/feats/phb:alert');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:alert');
    }

    #[Test]
    public function can_get_background_by_slug(): void
    {
        $background = Background::factory()->create([
            'slug' => 'phb:acolyte',
        ]);

        $response = $this->getJson('/api/v1/backgrounds/phb:acolyte');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:acolyte');
    }

    #[Test]
    public function slug_binding_still_works_for_spells(): void
    {
        $school = SpellSchool::factory()->create();
        $spell = Spell::factory()->create([
            'slug' => 'phb:magic-missile',
            'spell_school_id' => $school->id,
        ]);

        $response = $this->getJson('/api/v1/spells/phb:magic-missile');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'phb:magic-missile');
    }

    #[Test]
    public function id_binding_still_works_for_spells(): void
    {
        $school = SpellSchool::first() ?? SpellSchool::factory()->create();
        $spell = Spell::factory()->create([
            'slug' => 'phb:shield',
            'spell_school_id' => $school->id,
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $spell->id);
    }

    #[Test]
    public function returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/spells/phb:nonexistent-spell');

        $response->assertNotFound();
    }
}
