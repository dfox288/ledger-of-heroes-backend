<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class CharacterAvailableFeatsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function character_with_no_prerequisites_gets_all_non_prerequisite_feats()
    {
        $character = Character::factory()->create();

        // Create feats without prerequisites
        $feat1 = Feat::factory()->create(['name' => 'Alert']);
        $feat2 = Feat::factory()->create(['name' => 'Lucky']);

        // Create a feat with prerequisites that the character doesn't meet
        $feat3 = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat3->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Alert'])
            ->assertJsonFragment(['name' => 'Lucky'])
            ->assertJsonMissingPath('data.*.name', 'Heavy Armor Master');
    }

    #[Test]
    public function character_qualifies_for_feat_with_ability_score_prerequisite()
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'dexterity' => 10,
        ]);

        // Create feat with STR 13+ prerequisite
        $feat = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Heavy Armor Master']);
    }

    #[Test]
    public function character_does_not_qualify_for_feat_with_insufficient_ability_score()
    {
        $character = Character::factory()->create([
            'strength' => 12,
        ]);

        // Create feat with STR 13+ prerequisite
        $feat = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Heavy Armor Master']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_race_prerequisite()
    {
        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
            'full_slug' => 'phb:dwarf',
        ]);

        $character = Character::factory()->create([
            'race_slug' => 'phb:dwarf',
        ]);

        // Create feat with Dwarf prerequisite
        $feat = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Dwarven Fortitude']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_subrace_when_parent_race_required()
    {
        // Create parent race
        $elf = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'full_slug' => 'phb:elf',
        ]);

        // Create subrace
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'full_slug' => 'phb:high-elf',
            'parent_race_id' => $elf->id,
        ]);

        $character = Character::factory()->create([
            'race_slug' => 'phb:high-elf',
        ]);

        // Create feat that requires Elf (parent race)
        $feat = Feat::factory()->create(['name' => 'Elven Accuracy']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $elf->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Elven Accuracy']);
    }

    #[Test]
    public function character_does_not_qualify_for_feat_with_wrong_race()
    {
        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
            'full_slug' => 'phb:dwarf',
        ]);

        $elf = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'full_slug' => 'phb:elf',
        ]);

        $character = Character::factory()->create([
            'race_slug' => 'phb:elf',
        ]);

        // Create feat with Dwarf prerequisite
        $feat = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Dwarven Fortitude']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_proficiency_type_prerequisite()
    {
        $character = Character::factory()->create();

        // Use existing proficiency type from seeder
        $mediumArmor = ProficiencyType::where('slug', 'medium-armor')->first();

        // Grant character medium armor proficiency
        $character->proficiencies()->create([
            'proficiency_type_slug' => $mediumArmor->full_slug,
            'source' => 'class',
        ]);

        // Create feat that requires medium armor proficiency
        $feat = Feat::factory()->create(['name' => 'Moderately Armored']);
        $feat->prerequisites()->create([
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $mediumArmor->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Moderately Armored']);
    }

    #[Test]
    public function character_does_not_qualify_for_feat_without_required_proficiency()
    {
        $character = Character::factory()->create();

        // Use existing proficiency type from seeder
        $mediumArmor = ProficiencyType::where('slug', 'medium-armor')->first();

        // Create feat that requires medium armor proficiency (character doesn't have it)
        $feat = Feat::factory()->create(['name' => 'Moderately Armored']);
        $feat->prerequisites()->create([
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $mediumArmor->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Moderately Armored']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_skill_proficiency_prerequisite()
    {
        $character = Character::factory()->create();

        $athletics = Skill::where('name', 'Athletics')->first();

        // Grant character Athletics proficiency
        $character->proficiencies()->create([
            'skill_slug' => $athletics->full_slug,
            'source' => 'class',
        ]);

        // Create feat that requires Athletics proficiency
        $feat = Feat::factory()->create(['name' => 'Athlete']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Skill::class,
            'prerequisite_id' => $athletics->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Athlete']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_or_group_prerequisites()
    {
        // Test OR group: INT 13+ OR WIS 13+ (only one needs to be met)
        $character = Character::factory()->create([
            'intelligence' => 14,
            'wisdom' => 10,
        ]);

        $intelligence = AbilityScore::where('code', 'INT')->first();
        $wisdom = AbilityScore::where('code', 'WIS')->first();

        // Create feat with OR group (same group_id means OR)
        $feat = Feat::factory()->create(['name' => 'Ritual Caster']);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $intelligence->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $wisdom->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Ritual Caster']);
    }

    #[Test]
    public function character_does_not_qualify_for_feat_when_no_or_group_condition_met()
    {
        // Test OR group: INT 13+ OR WIS 13+ (neither met)
        $character = Character::factory()->create([
            'intelligence' => 12,
            'wisdom' => 10,
        ]);

        $intelligence = AbilityScore::where('code', 'INT')->first();
        $wisdom = AbilityScore::where('code', 'WIS')->first();

        // Create feat with OR group (same group_id means OR)
        $feat = Feat::factory()->create(['name' => 'Ritual Caster']);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $intelligence->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $wisdom->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Ritual Caster']);
    }

    #[Test]
    public function character_qualifies_for_feat_with_multiple_and_prerequisites()
    {
        // Test AND: STR 13+ AND DEX 13+ (both must be met, different group_ids)
        $character = Character::factory()->create([
            'strength' => 13,
            'dexterity' => 14,
        ]);

        $strength = AbilityScore::where('code', 'STR')->first();
        $dexterity = AbilityScore::where('code', 'DEX')->first();

        $feat = Feat::factory()->create(['name' => 'Dual Wielder']);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Dual Wielder']);
    }

    #[Test]
    public function character_does_not_qualify_when_one_and_prerequisite_fails()
    {
        // Test AND: STR 13+ AND DEX 13+ (only one met)
        $character = Character::factory()->create([
            'strength' => 13,
            'dexterity' => 12,
        ]);

        $strength = AbilityScore::where('code', 'STR')->first();
        $dexterity = AbilityScore::where('code', 'DEX')->first();

        $feat = Feat::factory()->create(['name' => 'Dual Wielder']);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $dexterity->id,
            'minimum_value' => 13,
            'group_id' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Dual Wielder']);
    }

    #[Test]
    public function endpoint_returns_feat_resources_with_full_details()
    {
        $character = Character::factory()->create();

        $feat = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'full_slug',
                        'description',
                        'prerequisites',
                        'sources',
                    ],
                ],
            ]);
    }

    #[Test]
    public function character_qualifies_for_feat_with_class_prerequisite()
    {
        $character = Character::factory()->create();

        $warlock = \App\Models\CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
        ]);

        // Add warlock class to character
        $character->characterClasses()->create([
            'class_slug' => $warlock->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Create feat that requires Warlock class
        $feat = Feat::factory()->create(['name' => 'Eldritch Adept']);
        $feat->prerequisites()->create([
            'prerequisite_type' => \App\Models\CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Eldritch Adept']);
    }

    #[Test]
    public function character_does_not_qualify_for_feat_without_required_class()
    {
        $character = Character::factory()->create();

        $warlock = \App\Models\CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
        ]);

        // Create feat that requires Warlock class (character doesn't have it)
        $feat = Feat::factory()->create(['name' => 'Eldritch Adept']);
        $feat->prerequisites()->create([
            'prerequisite_type' => \App\Models\CharacterClass::class,
            'prerequisite_id' => $warlock->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Eldritch Adept']);
    }

    #[Test]
    public function endpoint_returns_404_for_nonexistent_character()
    {
        $response = $this->getJson('/api/v1/characters/99999/available-feats');

        $response->assertNotFound();
    }

    #[Test]
    public function race_source_excludes_feats_with_ability_score_prerequisites()
    {
        // For race-granted feats (Variant Human, Custom Lineage), feats with
        // ability score prerequisites should be excluded entirely - RAW compliant
        $character = Character::factory()->create([
            'strength' => 14, // Would qualify normally
        ]);

        // Create a feat with STR 13+ prerequisite
        $feat = Feat::factory()->create(['name' => 'Grappler']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        // Create a feat without prerequisites
        $alertFeat = Feat::factory()->create(['name' => 'Alert']);

        // With source=race, ability score prerequisite feats are excluded
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats?source=race");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Alert'])
            ->assertJsonMissing(['name' => 'Grappler']); // Excluded despite meeting STR requirement
    }

    #[Test]
    public function asi_source_includes_feats_with_ability_score_prerequisites_if_qualified()
    {
        // For ASI-granted feats (level 4+), ability score prerequisites ARE checked
        $character = Character::factory()->create([
            'strength' => 14,
        ]);

        // Create a feat with STR 13+ prerequisite
        $feat = Feat::factory()->create(['name' => 'Grappler']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        // With source=asi, ability score prerequisites are checked (and met)
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats?source=asi");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Grappler']);
    }

    #[Test]
    public function race_source_still_filters_by_race_prerequisites()
    {
        // Race prerequisites should still be checked even for race source
        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'slug' => 'dwarf',
            'full_slug' => 'phb:dwarf',
        ]);

        $elf = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'full_slug' => 'phb:elf',
        ]);

        $character = Character::factory()->create([
            'race_slug' => 'phb:elf',
        ]);

        // Create feat requiring Dwarf race
        $feat = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats?source=race");

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Dwarven Fortitude']);
    }

    #[Test]
    public function race_source_includes_feats_with_only_race_prerequisites_if_matched()
    {
        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'slug' => 'halfling',
            'full_slug' => 'phb:halfling',
        ]);

        $character = Character::factory()->create([
            'race_slug' => 'phb:halfling',
        ]);

        // Create feat requiring Halfling race (no ability score prereq)
        $feat = Feat::factory()->create(['name' => 'Bountiful Luck']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $halfling->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats?source=race");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Bountiful Luck']);
    }

    #[Test]
    public function default_source_behaves_like_asi()
    {
        // When no source is specified, should behave like ASI (check ability scores)
        $character = Character::factory()->create([
            'strength' => 14,
        ]);

        $feat = Feat::factory()->create(['name' => 'Grappler']);
        $strength = AbilityScore::where('code', 'STR')->first();
        $feat->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
        ]);

        // No source parameter - should include Grappler since STR 14 >= 13
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-feats");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Grappler']);
    }
}
