<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterSubclassSpellsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    private ClassImporter $importer;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(ClassImporter::class);
        $this->parser = app(ClassXmlParser::class);

        // Create spells that will be referenced
        Spell::factory()->create(['name' => 'Bless', 'level' => 1]);
        Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);
        Spell::factory()->create(['name' => 'Lesser Restoration', 'level' => 2]);
        Spell::factory()->create(['name' => 'Spiritual Weapon', 'level' => 2]);
    }

    #[Test]
    public function imports_cleric_domain_spells(): void
    {
        $xml = $this->getClericDomainXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Life Domain subclass
        $lifeDomain = CharacterClass::where('name', 'Life Domain')->first();
        $this->assertNotNull($lifeDomain);

        // Find the domain feature
        $domainFeature = ClassFeature::where('class_id', $lifeDomain->id)
            ->where('feature_name', 'like', '%Life Domain%')
            ->first();
        $this->assertNotNull($domainFeature);

        // Check spell associations
        $spells = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $domainFeature->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $spells->count());

        // Check specific spell
        $blessSpell = $spells->first(fn ($s) => $s->spell->name === 'Bless');
        $this->assertNotNull($blessSpell);
        $this->assertEquals(1, $blessSpell->level_requirement);
    }

    private function getClericDomainXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <proficiency>Wisdom, Charisma</proficiency>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Divine Domain: Life Domain</name>
        <text>The Life domain focuses on the vibrant positive energy.

Domain Spells:
At each indicated cleric level, add the listed spells to your spells prepared.

Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon

Source: Player's Handbook (2014) p. 60</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}
