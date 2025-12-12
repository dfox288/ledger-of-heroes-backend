<?php

namespace Tests\Support\Traits;

use App\Models\AbilityScore;
use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Condition;
use App\Models\DamageType;
use App\Models\Feat;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Language;
use App\Models\Monster;
use App\Models\OptionalFeature;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellSchool;

trait CreatesHealthCheckFixtures
{
    /**
     * Fixtures created for health check tests.
     *
     * @var array<string, mixed>
     */
    protected array $fixtures = [];

    /**
     * Set up minimal fixtures for health check tests.
     * Call this in beforeEach() after seeding lookups.
     */
    protected function setUpHealthCheckFixtures(): void
    {
        // Entity fixtures (created via factories)
        $this->fixtures['spell'] = Spell::factory()->create();
        $this->fixtures['monster'] = Monster::factory()->create(['alignment' => 'Lawful Good']);
        $this->fixtures['class'] = CharacterClass::factory()->create();
        $this->fixtures['race'] = Race::factory()->create();
        $this->fixtures['background'] = Background::factory()->create();
        $this->fixtures['feat'] = Feat::factory()->create();
        $this->fixtures['item'] = Item::factory()->create();
        $this->fixtures['optionalFeature'] = OptionalFeature::factory()->create();

        // Lookup fixtures (from LookupSeeder - use firstOrFail for clear errors)
        $this->fixtures['abilityScore'] = AbilityScore::firstOrFail();
        $this->fixtures['condition'] = Condition::firstOrFail();
        $this->fixtures['damageType'] = DamageType::firstOrFail();
        $this->fixtures['itemProperty'] = ItemProperty::firstOrFail();
        $this->fixtures['itemType'] = ItemType::firstOrFail();
        $this->fixtures['language'] = Language::firstOrFail();
        $this->fixtures['proficiencyType'] = ProficiencyType::firstOrFail();
        $this->fixtures['size'] = Size::firstOrFail();
        $this->fixtures['skill'] = Skill::firstOrFail();
        $this->fixtures['source'] = Source::firstOrFail();
        $this->fixtures['spellSchool'] = SpellSchool::firstOrFail();

        // Derived lookups (from entity attributes)
        $this->fixtures['alignment'] = (object) ['slug' => str($this->fixtures['monster']->alignment)->slug()->toString()];
    }

    /**
     * Substitute path parameters with fixture values.
     *
     * @param  string  $path  The path with {param} placeholders
     * @param  array<string>  $params  Parameter names to substitute
     * @return string The path with actual values
     */
    protected function substitutePathParams(string $path, array $params): string
    {
        foreach ($params as $param) {
            if (! isset($this->fixtures[$param])) {
                throw new \RuntimeException("No fixture found for parameter: {$param}");
            }

            $fixture = $this->fixtures[$param];
            $value = is_object($fixture) ? ($fixture->slug ?? $fixture->id) : $fixture;

            $path = str_replace("{{$param}}", $value, $path);
        }

        return $path;
    }
}
