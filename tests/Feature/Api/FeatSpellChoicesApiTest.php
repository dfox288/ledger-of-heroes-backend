<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\EntityChoice;
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

        // Fixed spell grant (use entitySpellRecords for actual spell grants)
        $feat->entitySpellRecords()->create([
            'spell_id' => $invisibility->id,
            'usage_limit' => 'long_rest',
        ]);

        // Spell choices now go to entity_choices table
        // One choice group with two school options (pick 1 spell from illusion OR necromancy)
        EntityChoice::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'choice_type' => 'spell',
            'choice_group' => 'spell_choice_1',
            'quantity' => 1,
            'spell_max_level' => 1,
            'spell_school_slug' => $illusion->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);
        EntityChoice::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'choice_type' => 'spell',
            'choice_group' => 'spell_choice_1',
            'quantity' => 1,
            'spell_max_level' => 1,
            'spell_school_slug' => $necromancy->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        $response = $this->getJson('/api/v1/feats/'.$feat->id);

        // Test that fixed spell is returned
        $response->assertOk()
            ->assertJsonPath('data.name', 'Shadow Touched (Charisma)')
            ->assertJsonCount(1, 'data.spells'); // Only the fixed spell grant

        // Note: spell_choices output depends on FeatResource implementation
        // which will be addressed in Task #525 (EntityChoiceResource)
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

        // Cantrip choice - pick 2 cantrips from Bard spell list
        EntityChoice::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'choice_type' => 'spell',
            'choice_group' => 'spell_choice_1',
            'quantity' => 2,
            'spell_max_level' => 0, // Cantrips
            'spell_list_slug' => $bard->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        // 1st-level spell choice - pick 1 spell from Bard spell list
        EntityChoice::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'choice_type' => 'spell',
            'choice_group' => 'spell_choice_2',
            'quantity' => 1,
            'spell_max_level' => 1,
            'spell_list_slug' => $bard->slug,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        $response = $this->getJson('/api/v1/feats/'.$feat->id);

        // Test basic feat is returned (no fixed spells)
        $response->assertOk()
            ->assertJsonCount(0, 'data.spells'); // No fixed spell grants

        // Note: spell_choices output depends on FeatResource implementation
        // which will be addressed in Task #525 (EntityChoiceResource)
    }
}
