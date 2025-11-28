<?php

namespace Tests\Feature\Api;

use App\Http\Resources\ClassResource;
use App\Models\CharacterClass;
use App\Models\CharacterTrait;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ClassResourceCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function class_resource_includes_all_new_relationships()
    {
        // Create base class with all relationships
        $intAbility = $this->getAbilityScore('INT');
        $source = $this->getSource('PHB');

        $wizard = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
            'description' => 'A scholarly magic-user',
            'primary_ability' => 'Intelligence',
        ]);

        // Add source
        $wizard->sources()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $wizard->id,
            'source_id' => $source->id,
            'pages' => '110',
        ]);

        // Add proficiencies
        Proficiency::factory()->forEntity(CharacterClass::class, $wizard->id)->create([
            'proficiency_type' => 'Skill',
            'proficiency_name' => 'Arcana',
        ]);

        // Add traits
        CharacterTrait::factory()->forEntity(CharacterClass::class, $wizard->id)->create([
            'name' => 'Spellcasting',
            'description' => 'You can cast wizard spells',
        ]);

        // Add class features
        ClassFeature::factory()->create([
            'class_id' => $wizard->id,
            'level' => 1,
            'feature_name' => 'Arcane Recovery',
            'description' => 'You can regain some of your magical energy',
            'is_optional' => false,
            'sort_order' => 1,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $wizard->id,
            'level' => 2,
            'feature_name' => 'Arcane Tradition',
            'description' => 'Choose your arcane tradition',
            'is_optional' => true,
            'sort_order' => 1,
        ]);

        // Add level progression
        ClassLevelProgression::factory()->create([
            'class_id' => $wizard->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spell_slots_1st' => 2,
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $wizard->id,
            'level' => 2,
            'cantrips_known' => 3,
            'spell_slots_1st' => 3,
        ]);

        // Add counters
        ClassCounter::factory()->create([
            'class_id' => $wizard->id,
            'level' => 1,
            'counter_name' => 'Arcane Recovery',
            'counter_value' => 1,
            'reset_timing' => 'L',
        ]);

        // Create subclass
        $evocation = CharacterClass::factory()->create([
            'name' => 'School of Evocation',
            'slug' => 'wizard-evocation',
            'parent_class_id' => $wizard->id,
            'hit_die' => 6,
            'description' => 'Focus on evocation magic',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $evocation->id,
            'level' => 2,
            'feature_name' => 'Sculpt Spells',
            'description' => 'You can protect allies from your evocation spells',
            'is_optional' => false,
            'sort_order' => 1,
        ]);

        // Load all relationships
        $wizard->load([
            'spellcastingAbility',
            'sources.source',
            'proficiencies.proficiencyType',
            'proficiencies.skill',
            'traits.randomTables',
            'features',
            'levelProgression',
            'counters',
            'subclasses.features',
        ]);

        $resource = new ClassResource($wizard);
        $data = json_decode(json_encode($resource), true);

        // Assert basic fields
        $this->assertEquals('Wizard', $data['name']);
        $this->assertEquals('wizard', $data['slug']);
        $this->assertEquals(6, $data['hit_die']);
        $this->assertEquals('Intelligence', $data['primary_ability']);
        $this->assertNull($data['parent_class_id']);
        $this->assertTrue($data['is_base_class']);

        // Assert spellcasting ability
        $this->assertArrayHasKey('spellcasting_ability', $data);
        $this->assertEquals('INT', $data['spellcasting_ability']['code']);

        // Assert sources
        $this->assertArrayHasKey('sources', $data);
        $this->assertCount(1, $data['sources']);
        $this->assertEquals('PHB', $data['sources'][0]['code']);
        $this->assertEquals('110', $data['sources'][0]['pages']);

        // Assert proficiencies
        $this->assertCount(1, $data['proficiencies']);
        $this->assertEquals('Arcana', $data['proficiencies'][0]['proficiency_name']);

        // Assert traits
        $this->assertCount(1, $data['traits']);
        $this->assertEquals('Spellcasting', $data['traits'][0]['name']);

        // Assert features
        $this->assertCount(2, $data['features']);
        $this->assertEquals('Arcane Recovery', $data['features'][0]['feature_name']);
        $this->assertEquals(1, $data['features'][0]['level']);
        $this->assertFalse($data['features'][0]['is_optional']);
        $this->assertEquals('Arcane Tradition', $data['features'][1]['feature_name']);
        $this->assertTrue($data['features'][1]['is_optional']);

        // Assert level progression
        $this->assertCount(2, $data['level_progression']);
        $this->assertEquals(1, $data['level_progression'][0]['level']);
        $this->assertEquals(3, $data['level_progression'][0]['cantrips_known']);
        $this->assertEquals(2, $data['level_progression'][0]['spell_slots_1st']);
        $this->assertEquals(3, $data['level_progression'][1]['spell_slots_1st']);

        // Assert counters with formatted reset timing (grouped format)
        $this->assertCount(1, $data['counters']);
        $this->assertEquals('Arcane Recovery', $data['counters'][0]['name']);
        $this->assertEquals('Long Rest', $data['counters'][0]['reset_timing']);
        $this->assertCount(1, $data['counters'][0]['progression']);
        $this->assertEquals(1, $data['counters'][0]['progression'][0]['level']);
        $this->assertEquals(1, $data['counters'][0]['progression'][0]['value']);

        // Assert subclasses
        $this->assertCount(1, $data['subclasses']);
        $this->assertEquals('School of Evocation', $data['subclasses'][0]['name']);
        $this->assertEquals('wizard-evocation', $data['subclasses'][0]['slug']);
        $this->assertEquals($wizard->id, $data['subclasses'][0]['parent_class_id']);
        $this->assertFalse($data['subclasses'][0]['is_base_class']);
        $this->assertCount(1, $data['subclasses'][0]['features']);
        $this->assertEquals('Sculpt Spells', $data['subclasses'][0]['features'][0]['feature_name']);
    }

    #[Test]
    public function class_counter_reset_timing_formats_correctly()
    {
        $class = CharacterClass::factory()->create();

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'counter_name' => 'Ki Points',
            'counter_value' => 1,
            'reset_timing' => 'S',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'level' => 2,
            'counter_name' => 'Rage',
            'counter_value' => 2,
            'reset_timing' => 'L',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'level' => 3,
            'counter_name' => 'Bardic Inspiration',
            'counter_value' => 3,
            'reset_timing' => null,
        ]);

        $class->load('counters');
        $resource = new ClassResource($class);
        $data = json_decode(json_encode($resource), true);

        // Counters are grouped by name, so we have 3 separate counters
        $this->assertCount(3, $data['counters']);

        // Find each counter by name and verify reset timing
        $countersByName = collect($data['counters'])->keyBy('name');

        $this->assertEquals('Short Rest', $countersByName['Ki Points']['reset_timing']);
        $this->assertEquals(1, $countersByName['Ki Points']['progression'][0]['level']);
        $this->assertEquals(1, $countersByName['Ki Points']['progression'][0]['value']);

        $this->assertEquals('Long Rest', $countersByName['Rage']['reset_timing']);
        $this->assertEquals(2, $countersByName['Rage']['progression'][0]['level']);
        $this->assertEquals(2, $countersByName['Rage']['progression'][0]['value']);

        $this->assertEquals('Does Not Reset', $countersByName['Bardic Inspiration']['reset_timing']);
        $this->assertEquals(3, $countersByName['Bardic Inspiration']['progression'][0]['level']);
        $this->assertEquals(3, $countersByName['Bardic Inspiration']['progression'][0]['value']);
    }

    #[Test]
    public function class_resource_handles_empty_relationships_gracefully()
    {
        // Create a minimal class with no relationships
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'spellcasting_ability_id' => null,
            'parent_class_id' => null,
        ]);

        $resource = new ClassResource($class);
        $data = json_decode(json_encode($resource), true);

        $this->assertEquals('Fighter', $data['name']);
        $this->assertEquals('fighter', $data['slug']);
        $this->assertEquals(10, $data['hit_die']);
        $this->assertNull($data['parent_class_id']);
        $this->assertTrue($data['is_base_class']);

        // These should be missing because relationships aren't loaded
        // spellcasting_ability won't be in JSON when spellcasting_ability_id is null
        $this->assertArrayNotHasKey('spellcasting_ability', $data);
        $this->assertArrayNotHasKey('proficiencies', $data);
        $this->assertArrayNotHasKey('traits', $data);
        $this->assertArrayNotHasKey('features', $data);
        $this->assertArrayNotHasKey('level_progression', $data);
        $this->assertArrayNotHasKey('counters', $data);
        $this->assertArrayNotHasKey('subclasses', $data);
    }
}
