<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\EntityChoice;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for equipment choices on class API endpoint.
 *
 * Verifies that /api/v1/classes/{slug} returns equipment_choices field
 * containing the available starting equipment options from entity_choices table.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/529
 */
#[Group('feature-db')]
class ClassEquipmentChoicesApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function class_show_endpoint_returns_equipment_choices(): void
    {
        // Create a test class
        $fighter = CharacterClass::factory()->create([
            'name' => 'Test Fighter',
            'slug' => 'test:fighter',
            'hit_die' => 10,
        ]);

        // Create test items
        $chainMail = Item::factory()->create([
            'name' => 'Chain Mail',
            'slug' => 'test:chain-mail',
        ]);

        $leatherArmor = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'test:leather-armor',
        ]);

        // Create equipment choices - choice group 1: chain mail OR leather armor
        EntityChoice::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighter->id,
            'choice_type' => 'equipment',
            'choice_group' => 'armor_choice',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => $chainMail->slug,
            'description' => 'chain mail',
            'level_granted' => 1,
        ]);

        EntityChoice::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $fighter->id,
            'choice_type' => 'equipment',
            'choice_group' => 'armor_choice',
            'choice_option' => 2,
            'target_type' => 'item',
            'target_slug' => $leatherArmor->slug,
            'description' => 'leather armor',
            'level_granted' => 1,
        ]);

        // Make request to class show endpoint
        $response = $this->getJson("/api/v1/classes/{$fighter->slug}");

        // Verify response structure includes equipment_choices
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'slug',
                    'name',
                    'equipment_choices' => [
                        '*' => [
                            'choice_group',
                            'options' => [
                                '*' => [
                                    'option',
                                    'description',
                                    'target_slug',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify the equipment choices contain our test data
        $equipmentChoices = $response->json('data.equipment_choices');
        $this->assertCount(1, $equipmentChoices, 'Should have 1 choice group');

        $armorChoice = $equipmentChoices[0];
        $this->assertEquals('armor_choice', $armorChoice['choice_group']);
        $this->assertCount(2, $armorChoice['options'], 'Should have 2 options (chain mail, leather armor)');
    }

    #[Test]
    public function class_with_no_equipment_choices_returns_empty_array(): void
    {
        // Create a class without any equipment choices
        $monk = CharacterClass::factory()->create([
            'name' => 'Test Monk',
            'slug' => 'test:monk',
            'hit_die' => 8,
        ]);

        $response = $this->getJson("/api/v1/classes/{$monk->slug}");

        $response->assertOk()
            ->assertJsonPath('data.equipment_choices', []);
    }

    #[Test]
    public function equipment_choices_are_grouped_by_choice_group(): void
    {
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Test Barbarian',
            'slug' => 'test:barbarian',
            'hit_die' => 12,
        ]);

        // Create 2 different choice groups
        // Group 1: weapon choice
        EntityChoice::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $barbarian->id,
            'choice_type' => 'equipment',
            'choice_group' => 'weapon_choice',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'test:greataxe',
            'description' => 'a greataxe',
        ]);

        // Group 2: secondary weapon choice
        EntityChoice::factory()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $barbarian->id,
            'choice_type' => 'equipment',
            'choice_group' => 'secondary_weapon',
            'choice_option' => 1,
            'target_type' => 'item',
            'target_slug' => 'test:handaxe',
            'description' => 'two handaxes',
        ]);

        $response = $this->getJson("/api/v1/classes/{$barbarian->slug}");

        $response->assertOk();

        $equipmentChoices = $response->json('data.equipment_choices');
        $this->assertCount(2, $equipmentChoices, 'Should have 2 choice groups');

        $choiceGroups = array_column($equipmentChoices, 'choice_group');
        $this->assertContains('weapon_choice', $choiceGroups);
        $this->assertContains('secondary_weapon', $choiceGroups);
    }
}
