<?php

namespace Tests\Feature\Importers;

use App\Enums\ResetTiming;
use App\Models\CharacterTrait;
use App\Models\EntityCounter;
use App\Models\Race;
use App\Services\Importers\RaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for creating EntityCounter records for racial traits with usage limits.
 *
 * Covers traits like Breath Weapon (short rest), Relentless Endurance (long rest),
 * Fey Step (short/long rest), etc.
 */
#[Group('importers')]
class RaceImporterTraitCounterTest extends TestCase
{
    use RefreshDatabase;

    private RaceImporter $importer;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new RaceImporter;
    }

    #[Test]
    public function it_creates_counter_for_breath_weapon_trait(): void
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Breath Weapon',
                    'category' => 'species',
                    'description' => 'You can use your action to exhale destructive energy. After you use your breath weapon, you can\'t use it again until you complete a short or long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::SHORT_REST,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        // Get the created trait
        $breathWeaponTrait = $race->traits->where('name', 'Breath Weapon')->first();
        $this->assertNotNull($breathWeaponTrait);

        // Verify counter was created for the trait
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $breathWeaponTrait->id)
            ->first();

        $this->assertNotNull($counter, 'Counter should be created for Breath Weapon trait');
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('S', $counter->reset_timing);
        $this->assertEquals(1, $counter->level);
    }

    #[Test]
    public function it_creates_counter_for_relentless_endurance_trait(): void
    {
        $raceData = [
            'name' => 'Half-Orc',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Relentless Endurance',
                    'category' => 'species',
                    'description' => 'When you are reduced to 0 hit points but not killed outright, you can drop to 1 hit point instead. You can\'t use this feature again until you finish a long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::LONG_REST,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Relentless Endurance')->first();
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('L', $counter->reset_timing);
    }

    #[Test]
    public function it_creates_counter_for_fey_step_trait(): void
    {
        $raceData = [
            'name' => 'Eladrin',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Fey Step',
                    'category' => 'subspecies',
                    'description' => 'You can cast the misty step spell once using this trait. You regain the ability to do so when you finish a short or long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::SHORT_REST,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Fey Step')->first();
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('S', $counter->reset_timing);
    }

    #[Test]
    public function it_creates_counter_for_hidden_step_trait(): void
    {
        $raceData = [
            'name' => 'Firbolg',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Hidden Step',
                    'category' => 'species',
                    'description' => 'As a bonus action, you can magically turn invisible until the start of your next turn. Once you use this trait, you can\'t use it again until you finish a short or long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::SHORT_REST,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Hidden Step')->first();
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('S', $counter->reset_timing);
    }

    #[Test]
    public function it_does_not_create_counter_for_traits_without_usage_limits(): void
    {
        $raceData = [
            'name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Darkvision',
                    'category' => 'species',
                    'description' => 'You can see in dim light within 60 feet of you as if it were bright light.',
                    'sort_order' => 0,
                    'max_uses' => null,
                    'resets_on' => null,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Darkvision')->first();

        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->first();

        $this->assertNull($counter, 'No counter should be created for traits without usage limits');
    }

    #[Test]
    public function it_updates_counter_on_reimport(): void
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Breath Weapon',
                    'category' => 'species',
                    'description' => 'After you use your breath weapon, you can\'t use it again until you complete a short or long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::SHORT_REST,
                ],
            ],
            'sources' => [],
        ];

        // Import twice
        $this->importer->import($raceData);
        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Breath Weapon')->first();

        // Should only have one counter (updated, not duplicated)
        $counters = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->get();

        $this->assertCount(1, $counters);
        $this->assertEquals(1, $counters->first()->counter_value);
    }

    #[Test]
    public function it_stores_resets_on_in_trait_record(): void
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Breath Weapon',
                    'category' => 'species',
                    'description' => 'After you use your breath weapon, you can\'t use it again until you complete a short or long rest.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::SHORT_REST,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Breath Weapon')->first();

        // The resets_on should be stored on the trait itself
        $this->assertEquals(ResetTiming::SHORT_REST, $trait->resets_on);
    }

    #[Test]
    public function it_creates_counters_from_xml_file_import(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can use your action to exhale destructive energy. After you use your breath weapon, you can't use it again until you complete a short or long rest.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_counter_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race);

        $breathWeaponTrait = $race->traits->where('name', 'Breath Weapon')->first();
        $this->assertNotNull($breathWeaponTrait);

        // Verify counter was created
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $breathWeaponTrait->id)
            ->first();

        $this->assertNotNull($counter, 'Counter should be created from XML import');
        $this->assertEquals(1, $counter->counter_value);
        $this->assertEquals('S', $counter->reset_timing);

        // Verify trait has resets_on
        $this->assertEquals(ResetTiming::SHORT_REST, $breathWeaponTrait->resets_on);
    }

    #[Test]
    public function it_creates_counter_with_dawn_reset(): void
    {
        $raceData = [
            'name' => 'TestRace',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Daily Power',
                    'category' => 'species',
                    'description' => 'You can use this feature. You regain use of this trait at dawn.',
                    'sort_order' => 0,
                    'max_uses' => 1,
                    'resets_on' => ResetTiming::DAWN,
                ],
            ],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $trait = $race->traits->where('name', 'Daily Power')->first();
        $counter = EntityCounter::where('reference_type', CharacterTrait::class)
            ->where('reference_id', $trait->id)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals('D', $counter->reset_timing);
    }
}
