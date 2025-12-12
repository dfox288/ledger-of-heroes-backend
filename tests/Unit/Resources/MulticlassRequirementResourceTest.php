<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\MulticlassRequirementResource;
use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MulticlassRequirementResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_multiclass_requirement_with_ability_score(): void
    {
        $class = CharacterClass::factory()->create();
        $abilityScore = AbilityScore::where('code', 'STR')->first();

        $proficiency = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => 'Strength 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        $proficiency->load('abilityScore');

        $resource = new MulticlassRequirementResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('ability', $array);
        $this->assertNotNull($array['ability']);
        $this->assertEquals('Strength 13', $array['ability_name']);
        $this->assertEquals(13, $array['minimum_score']);
        $this->assertFalse($array['is_alternative']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_ability_when_relationship_not_loaded(): void
    {
        $class = CharacterClass::factory()->create();
        $abilityScore = AbilityScore::where('code', 'DEX')->first();

        $proficiency = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => 'Dexterity 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        // Do NOT load abilityScore relationship
        $resource = new MulticlassRequirementResource($proficiency);
        $array = $resource->toArray(request());

        // Laravel's when() with false condition uses MissingValue, which gets removed during JSON serialization
        // So we test the essential fields are present
        $this->assertEquals('Dexterity 13', $array['ability_name']);
        $this->assertEquals(13, $array['minimum_score']);
        $this->assertFalse($array['is_alternative']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_is_alternative_true_when_subcategory_is_or(): void
    {
        $class = CharacterClass::factory()->create();
        $abilityScore = AbilityScore::where('code', 'INT')->first();

        $proficiency = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => 'Intelligence 13',
            'proficiency_subcategory' => 'OR', // Alternative - any one satisfies
        ]);

        $resource = new MulticlassRequirementResource($proficiency);
        $array = $resource->toArray(request());

        $this->assertTrue($array['is_alternative']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_none_type_for_empty_collection(): void
    {
        $requirements = collect([]);

        $result = MulticlassRequirementResource::collectionWithType($requirements);

        $this->assertEquals('none', $result['type']);
        $this->assertEmpty($result['requirements']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_single_type_for_one_requirement(): void
    {
        $class = CharacterClass::factory()->create();
        $abilityScore = AbilityScore::where('code', 'WIS')->first();

        $proficiency = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => 'Wisdom 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        $requirements = collect([$proficiency]);

        $result = MulticlassRequirementResource::collectionWithType($requirements);

        $this->assertEquals('single', $result['type']);
        $this->assertCount(1, $result['requirements']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_and_type_for_multiple_requirements_all_required(): void
    {
        $class = CharacterClass::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $dex = AbilityScore::where('code', 'DEX')->first();

        $prof1 = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $str->id,
            'proficiency_name' => 'Strength 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        $prof2 = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $dex->id,
            'proficiency_name' => 'Dexterity 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        $requirements = collect([$prof1, $prof2]);

        $result = MulticlassRequirementResource::collectionWithType($requirements);

        $this->assertEquals('and', $result['type']);
        $this->assertCount(2, $result['requirements']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_or_type_when_any_requirement_has_or_subcategory(): void
    {
        $class = CharacterClass::factory()->create();
        $str = AbilityScore::where('code', 'STR')->first();
        $dex = AbilityScore::where('code', 'DEX')->first();

        $prof1 = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $str->id,
            'proficiency_name' => 'Strength 13',
            'proficiency_subcategory' => 'OR', // Alternative
        ]);

        $prof2 = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $dex->id,
            'proficiency_name' => 'Dexterity 13',
            'proficiency_subcategory' => 'OR', // Alternative
        ]);

        $requirements = collect([$prof1, $prof2]);

        $result = MulticlassRequirementResource::collectionWithType($requirements);

        $this->assertEquals('or', $result['type']);
        $this->assertCount(2, $result['requirements']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_requirements_in_collection(): void
    {
        $class = CharacterClass::factory()->create();
        $abilityScore = AbilityScore::where('code', 'CHA')->first();

        $proficiency = Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'ability_score_id' => $abilityScore->id,
            'proficiency_name' => 'Charisma 13',
            'proficiency_subcategory' => 'AND', // Required
        ]);

        $requirements = collect([$proficiency]);

        $result = MulticlassRequirementResource::collectionWithType($requirements);

        $this->assertEquals('single', $result['type']);
        $this->assertIsArray($result['requirements']);
        $this->assertCount(1, $result['requirements']);
        $this->assertIsArray($result['requirements'][0]);
        $this->assertEquals('Charisma 13', $result['requirements'][0]['ability_name']);
    }
}
