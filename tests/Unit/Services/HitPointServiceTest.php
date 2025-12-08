<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use App\Services\CharacterStatCalculator;
use App\Services\HitPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HitPointServiceTest extends TestCase
{
    use RefreshDatabase;

    private HitPointService $service;

    private CharacterClass $fighter;

    private CharacterClass $wizard;

    private CharacterClass $barbarian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HitPointService(app(CharacterStatCalculator::class));

        $this->fighter = CharacterClass::factory()->create([
            'slug' => 'fighter',
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $this->wizard = CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        $this->barbarian = CharacterClass::factory()->create([
            'slug' => 'barbarian',
            'name' => 'Barbarian',
            'hit_die' => 12,
            'parent_class_id' => null,
        ]);
    }

    // =====================
    // calculateStartingHp Tests
    // =====================

    #[Test]
    public function it_calculates_fighter_starting_hp_with_positive_con(): void
    {
        $character = Character::factory()->create(['constitution' => 14]); // +2

        $hp = $this->service->calculateStartingHp($character, $this->fighter);

        $this->assertEquals(12, $hp); // 10 + 2
    }

    #[Test]
    public function it_calculates_wizard_starting_hp_with_low_con(): void
    {
        $character = Character::factory()->create(['constitution' => 8]); // -1

        $hp = $this->service->calculateStartingHp($character, $this->wizard);

        $this->assertEquals(5, $hp); // 6 - 1
    }

    #[Test]
    public function it_calculates_barbarian_starting_hp_with_high_con(): void
    {
        $character = Character::factory()->create(['constitution' => 16]); // +3

        $hp = $this->service->calculateStartingHp($character, $this->barbarian);

        $this->assertEquals(15, $hp); // 12 + 3
    }

    #[Test]
    public function it_enforces_minimum_1_hp_with_very_low_con(): void
    {
        $character = Character::factory()->create(['constitution' => 3]); // -4

        $hp = $this->service->calculateStartingHp($character, $this->wizard);

        $this->assertEquals(2, $hp); // max(1, 6 - 4) = 2
    }

    #[Test]
    public function it_handles_null_constitution_as_10(): void
    {
        $character = Character::factory()->create(['constitution' => null]);

        $hp = $this->service->calculateStartingHp($character, $this->fighter);

        $this->assertEquals(10, $hp); // 10 + 0
    }

    // =====================
    // calculateAverageHpGain Tests
    // =====================

    #[Test]
    public function it_calculates_d10_average_with_positive_con(): void
    {
        $hp = $this->service->calculateAverageHpGain(10, 14); // +2 CON

        $this->assertEquals(8, $hp); // (10/2 + 1) + 2 = 6 + 2
    }

    #[Test]
    public function it_calculates_d6_average_with_positive_con(): void
    {
        $hp = $this->service->calculateAverageHpGain(6, 12); // +1 CON

        $this->assertEquals(5, $hp); // (6/2 + 1) + 1 = 4 + 1
    }

    #[Test]
    public function it_calculates_d12_average_with_negative_con(): void
    {
        $hp = $this->service->calculateAverageHpGain(12, 8); // -1 CON

        $this->assertEquals(6, $hp); // (12/2 + 1) - 1 = 7 - 1
    }

    #[Test]
    public function it_enforces_minimum_1_hp_on_average(): void
    {
        $hp = $this->service->calculateAverageHpGain(6, 3); // -4 CON

        $this->assertEquals(1, $hp); // max(1, 4 - 4) = 1
    }

    // =====================
    // calculateRolledHpGain Tests
    // =====================

    #[Test]
    public function it_calculates_hp_from_roll_with_con_modifier(): void
    {
        $hp = $this->service->calculateRolledHpGain(7, 10, 14); // +2 CON

        $this->assertEquals(9, $hp); // 7 + 2
    }

    #[Test]
    public function it_enforces_minimum_1_hp_on_low_roll(): void
    {
        $hp = $this->service->calculateRolledHpGain(1, 6, 3); // -4 CON

        $this->assertEquals(1, $hp); // max(1, 1 - 4)
    }

    #[Test]
    public function it_rejects_roll_below_1(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateRolledHpGain(0, 10, 10);
    }

    #[Test]
    public function it_rejects_roll_above_hit_die(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateRolledHpGain(11, 10, 10);
    }

    #[Test]
    public function it_accepts_maximum_roll(): void
    {
        $hp = $this->service->calculateRolledHpGain(10, 10, 14); // +2 CON

        $this->assertEquals(12, $hp); // 10 + 2
    }

    // =====================
    // recalculateForConChange Tests
    // =====================

    #[Test]
    public function it_increases_hp_when_con_increases(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 14 (+2) -> CON 16 (+3) = +1 per level = +5 HP
        $result = $this->service->recalculateForConChange($character, 14, 16);

        $this->assertEquals(5, $result['adjustment']);
        $this->assertEquals(55, $result['new_max_hp']);
        $this->assertEquals(55, $result['new_current_hp']);
    }

    #[Test]
    public function it_decreases_hp_when_con_decreases(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 55,
            'current_hit_points' => 55,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 16 (+3) -> CON 14 (+2) = -1 per level = -5 HP
        $result = $this->service->recalculateForConChange($character, 16, 14);

        $this->assertEquals(-5, $result['adjustment']);
        $this->assertEquals(50, $result['new_max_hp']);
        $this->assertEquals(50, $result['new_current_hp']);
    }

    #[Test]
    public function it_caps_current_hp_at_new_max_when_con_decreases(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 55,
            'current_hit_points' => 30, // Already damaged
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->recalculateForConChange($character, 16, 14);

        $this->assertEquals(50, $result['new_max_hp']);
        $this->assertEquals(30, $result['new_current_hp']); // Stays at 30 (below new max)
    }

    #[Test]
    public function it_returns_no_change_when_con_modifier_unchanged(): void
    {
        $character = Character::factory()->create([
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // CON 14 -> 15 both have +2 modifier
        $result = $this->service->recalculateForConChange($character, 14, 15);

        $this->assertEquals(0, $result['adjustment']);
    }

    // =====================
    // getHitDieForLevel Tests
    // =====================

    #[Test]
    public function it_returns_primary_class_hit_die_for_single_class(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals(10, $this->service->getHitDieForLevel($character, 1));
        $this->assertEquals(10, $this->service->getHitDieForLevel($character, 5));
    }

    #[Test]
    public function it_returns_correct_hit_die_for_multiclass_levels(): void
    {
        $character = Character::factory()->create();

        // Fighter 3 / Wizard 2 = total level 5
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->wizard->full_slug,
            'level' => 2,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Levels 1-3 are Fighter (d10)
        $this->assertEquals(10, $this->service->getHitDieForLevel($character, 1));
        $this->assertEquals(10, $this->service->getHitDieForLevel($character, 3));

        // Levels 4-5 are Wizard (d6)
        $this->assertEquals(6, $this->service->getHitDieForLevel($character, 4));
        $this->assertEquals(6, $this->service->getHitDieForLevel($character, 5));
    }

    // =====================
    // getFeatHpBonus Tests
    // =====================

    #[Test]
    public function it_returns_hp_bonus_from_feat_with_hp_modifier(): void
    {
        $character = Character::factory()->create();

        // Create Tough feat with hit_points_per_level modifier
        $toughFeat = Feat::factory()->create([
            'slug' => 'tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        // Grant the feat to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $toughFeat->id,
            'feature_slug' => $toughFeat->full_slug,
            'source' => 'asi_choice',
        ]);

        $bonus = $this->service->getFeatHpBonus($character);

        $this->assertEquals(2, $bonus);
    }

    #[Test]
    public function it_returns_zero_when_no_feats_with_hp_modifiers(): void
    {
        $character = Character::factory()->create();

        // Create a feat without HP modifier
        $alertFeat = Feat::factory()->create([
            'slug' => 'alert',
            'name' => 'Alert',
        ]);

        // Grant the feat to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $alertFeat->id,
            'feature_slug' => $alertFeat->full_slug,
            'source' => 'asi_choice',
        ]);

        $bonus = $this->service->getFeatHpBonus($character);

        $this->assertEquals(0, $bonus);
    }

    #[Test]
    public function it_sums_hp_bonuses_from_multiple_feats(): void
    {
        $character = Character::factory()->create();

        // Create first feat with HP modifier
        $toughFeat = Feat::factory()->create([
            'slug' => 'tough',
            'name' => 'Tough',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $toughFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 2,
        ]);

        // Create second hypothetical feat with HP modifier
        $durabilitFeat = Feat::factory()->create([
            'slug' => 'durability',
            'name' => 'Durability',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $durabilitFeat->id,
            'modifier_category' => 'hit_points_per_level',
            'value' => 1,
        ]);

        // Grant both feats to the character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $toughFeat->id,
            'feature_slug' => $toughFeat->full_slug,
            'source' => 'asi_choice',
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $durabilitFeat->id,
            'feature_slug' => $durabilitFeat->full_slug,
            'source' => 'asi_choice',
        ]);

        $bonus = $this->service->getFeatHpBonus($character);

        $this->assertEquals(3, $bonus); // 2 + 1
    }

    // =====================
    // getRaceHpBonus Tests
    // =====================

    #[Test]
    public function it_returns_hp_bonus_from_race_with_hp_modifier(): void
    {
        // Create Hill Dwarf race with HP modifier
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->full_slug,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(1, $bonus);
    }

    #[Test]
    public function it_returns_zero_when_race_has_no_hp_modifier(): void
    {
        $human = Race::factory()->create([
            'slug' => 'human',
            'full_slug' => 'human',
            'name' => 'Human',
        ]);

        $character = Character::factory()->create([
            'race_slug' => $human->full_slug,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(0, $bonus);
    }

    #[Test]
    public function it_returns_zero_when_character_has_no_race(): void
    {
        $character = Character::factory()->create([
            'race_slug' => null,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(0, $bonus);
    }

    #[Test]
    public function it_includes_parent_race_hp_modifiers_for_subraces(): void
    {
        // Create base Dwarf race (no HP modifier)
        $dwarf = Race::factory()->create([
            'slug' => 'dwarf',
            'full_slug' => 'dwarf',
            'name' => 'Dwarf',
            'parent_race_id' => null,
        ]);

        // Create Hill Dwarf subrace with HP modifier
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
            'parent_race_id' => $dwarf->id,
        ]);

        // Hill Dwarf gets +1 HP per level
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->full_slug,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(1, $bonus);
    }

    #[Test]
    public function it_inherits_parent_race_hp_bonus_when_subrace_has_none(): void
    {
        // Create Dwarf parent race with HP modifier
        $dwarf = Race::factory()->create([
            'slug' => 'dwarf',
            'full_slug' => 'dwarf',
            'name' => 'Dwarf',
            'parent_race_id' => null,
        ]);

        // Parent race gives +1 HP per level
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $dwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        // Create Mountain Dwarf subrace (NO HP modifier of its own)
        $mountainDwarf = Race::factory()->create([
            'slug' => 'dwarf-mountain',
            'full_slug' => 'dwarf-mountain',
            'name' => 'Mountain Dwarf',
            'parent_race_id' => $dwarf->id,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $mountainDwarf->full_slug,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(1, $bonus); // Should inherit parent's +1 HP
    }

    #[Test]
    public function it_combines_parent_and_subrace_hp_modifiers(): void
    {
        // Hypothetical scenario: both parent and subrace have HP modifiers
        $baseRace = Race::factory()->create([
            'slug' => 'hardy-folk',
            'full_slug' => 'hardy-folk',
            'name' => 'Hardy Folk',
            'parent_race_id' => null,
        ]);

        // Parent race gives +1 HP per level
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $baseRace->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $subrace = Race::factory()->create([
            'slug' => 'hardy-folk-mountain',
            'full_slug' => 'hardy-folk-mountain',
            'name' => 'Mountain Hardy Folk',
            'parent_race_id' => $baseRace->id,
        ]);

        // Subrace adds another +1 HP per level
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $subrace->full_slug,
        ]);

        $bonus = $this->service->getRaceHpBonus($character);

        $this->assertEquals(2, $bonus); // 1 + 1 from parent and subrace
    }

    // =====================
    // recalculateForRaceChange Tests
    // =====================

    #[Test]
    public function it_increases_hp_when_changing_to_race_with_hp_bonus(): void
    {
        // Create Human (no HP bonus)
        $human = Race::factory()->create([
            'slug' => 'human',
            'full_slug' => 'human',
            'name' => 'Human',
        ]);

        // Create Hill Dwarf (with HP bonus)
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $human->full_slug,
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Human (0) -> Hill Dwarf (+1 per level) = +5 HP
        $result = $this->service->recalculateForRaceChange($character, $human->full_slug, $hillDwarf->full_slug);

        $this->assertEquals(5, $result['adjustment']); // +1 per level × 5 levels
        $this->assertEquals(55, $result['new_max_hp']);
        $this->assertEquals(55, $result['new_current_hp']);
    }

    #[Test]
    public function it_decreases_hp_when_changing_from_race_with_hp_bonus(): void
    {
        // Create Human (no HP bonus)
        $human = Race::factory()->create([
            'slug' => 'human',
            'full_slug' => 'human',
            'name' => 'Human',
        ]);

        // Create Hill Dwarf (with HP bonus)
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->full_slug,
            'max_hit_points' => 55, // Includes +5 from Hill Dwarf
            'current_hit_points' => 55,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Hill Dwarf (+1) -> Human (0) = -5 HP
        $result = $this->service->recalculateForRaceChange($character, $hillDwarf->full_slug, $human->full_slug);

        $this->assertEquals(-5, $result['adjustment']);
        $this->assertEquals(50, $result['new_max_hp']);
        $this->assertEquals(50, $result['new_current_hp']);
    }

    #[Test]
    public function it_returns_no_change_when_switching_races_with_same_hp_bonus(): void
    {
        // Create two races with no HP bonus
        $human = Race::factory()->create([
            'slug' => 'human',
            'full_slug' => 'human',
            'name' => 'Human',
        ]);

        $elf = Race::factory()->create([
            'slug' => 'elf',
            'full_slug' => 'elf',
            'name' => 'Elf',
        ]);

        $character = Character::factory()->create([
            'race_slug' => $human->full_slug,
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->recalculateForRaceChange($character, $human->full_slug, $elf->full_slug);

        $this->assertEquals(0, $result['adjustment']);
        $this->assertEquals(50, $result['new_max_hp']);
        $this->assertEquals(50, $result['new_current_hp']);
    }

    #[Test]
    public function it_caps_current_hp_at_new_max_when_race_hp_decreases(): void
    {
        // Create Human (no HP bonus)
        $human = Race::factory()->create([
            'slug' => 'human',
            'full_slug' => 'human',
            'name' => 'Human',
        ]);

        // Create Hill Dwarf (with HP bonus)
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => $hillDwarf->full_slug,
            'max_hit_points' => 55,
            'current_hit_points' => 30, // Already damaged
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->recalculateForRaceChange($character, $hillDwarf->full_slug, $human->full_slug);

        $this->assertEquals(50, $result['new_max_hp']);
        $this->assertEquals(30, $result['new_current_hp']); // Stays at 30 (below new max)
    }

    #[Test]
    public function it_handles_changing_from_no_race_to_race_with_hp_bonus(): void
    {
        // Create Hill Dwarf (with HP bonus)
        $hillDwarf = Race::factory()->create([
            'slug' => 'dwarf-hill',
            'full_slug' => 'dwarf-hill',
            'name' => 'Hill Dwarf',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $hillDwarf->id,
            'modifier_category' => 'hp',
            'value' => 1,
        ]);

        $character = Character::factory()->create([
            'race_slug' => null,
            'max_hit_points' => 50,
            'current_hit_points' => 50,
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->full_slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // No race (0) -> Hill Dwarf (+1 per level) = +5 HP
        $result = $this->service->recalculateForRaceChange($character, null, $hillDwarf->full_slug);

        $this->assertEquals(5, $result['adjustment']);
        $this->assertEquals(55, $result['new_max_hp']);
        $this->assertEquals(55, $result['new_current_hp']);
    }
}
