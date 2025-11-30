<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class FeatSpellChoicesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_spell_choices_for_feat_with_school_constraint()
    {
        // Create test data
        $illusion = SpellSchool::factory()->create([
            'code' => 'IL',
            'name' => 'Illusion',
            'description' => 'Deception spells',
        ]);
        $necromancy = SpellSchool::factory()->create([
            'code' => 'NE',
            'name' => 'Necromancy',
            'description' => 'Death and undeath spells',
        ]);
        $invisibility = Spell::factory()->create(['name' => 'Invisibility']);

        $feat = Feat::factory()->create([
            'name' => 'Shadow Touched (Charisma)',
            'slug' => 'shadow-touched-charisma',
        ]);

        // Fixed spell
        $feat->spells()->create([
            'spell_id' => $invisibility->id,
            'is_choice' => false,
            'usage_limit' => 'long_rest',
        ]);

        // Spell choices (school-constrained)
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_1',
            'max_level' => 1,
            'school_id' => $illusion->id,
        ]);
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_1',
            'max_level' => 1,
            'school_id' => $necromancy->id,
        ]);

        $response = $this->getJson('/api/v1/feats/' . $feat->id);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Shadow Touched (Charisma)')
            ->assertJsonCount(3, 'data.spells')
            ->assertJsonPath('data.spell_choices.0.choice_group', 'spell_choice_1')
            ->assertJsonPath('data.spell_choices.0.choice_count', 1)
            ->assertJsonPath('data.spell_choices.0.max_level', 1)
            ->assertJsonCount(2, 'data.spell_choices.0.allowed_schools');
    }

    #[Test]
    public function it_returns_spell_choices_for_feat_with_class_constraint()
    {
        $bard = CharacterClass::factory()->create([
            'name' => 'Bard',
            'slug' => 'bard',
        ]);

        $feat = Feat::factory()->create([
            'name' => 'Magic Initiate (Bard)',
            'slug' => 'magic-initiate-bard',
        ]);

        // Cantrip choice
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
            'choice_group' => 'spell_choice_1',
            'max_level' => 0,
            'class_id' => $bard->id,
        ]);

        // 1st-level spell choice
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_2',
            'max_level' => 1,
            'class_id' => $bard->id,
        ]);

        $response = $this->getJson('/api/v1/feats/' . $feat->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data.spells')
            ->assertJsonCount(2, 'data.spell_choices')
            ->assertJsonPath('data.spell_choices.0.allowed_class.name', 'Bard');
    }
}
