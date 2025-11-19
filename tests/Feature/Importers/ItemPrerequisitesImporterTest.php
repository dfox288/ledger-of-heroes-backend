<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Item;
use App\Services\Importers\ItemImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemPrerequisitesImporterTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_item_with_strength_requirement_as_prerequisite()
    {
        // Arrange: XML with strength requirement
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Plate Armor</name>
        <type>HA</type>
        <ac>18</ac>
        <strength>15</strength>
        <stealth>YES</stealth>
        <text>Source: Player's Handbook p. 145</text>
    </item>
</compendium>
XML;

        // Act: Import the item
        $importer = new ItemImporter;
        $importer->importFromFile($this->createTempXmlFile($xml));

        // Assert: Item was created
        $item = Item::where('slug', 'plate-armor')->first();
        $this->assertNotNull($item);
        $this->assertEquals('Plate Armor', $item->name);
        $this->assertEquals(15, $item->strength_requirement);

        // Assert: Prerequisite was created
        $prerequisite = EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item->id)
            ->first();

        $this->assertNotNull($prerequisite);

        $strAbilityScore = AbilityScore::where('code', 'STR')->first();
        $this->assertEquals(AbilityScore::class, $prerequisite->prerequisite_type);
        $this->assertEquals($strAbilityScore->id, $prerequisite->prerequisite_id);
        $this->assertEquals(15, $prerequisite->minimum_value);
        $this->assertEquals(1, $prerequisite->group_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_create_prerequisite_for_item_without_strength_requirement()
    {
        // Arrange: XML without strength requirement
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Leather Armor</name>
        <type>LA</type>
        <ac>11</ac>
        <text>Source: Player's Handbook p. 144</text>
    </item>
</compendium>
XML;

        // Act: Import the item
        $importer = new ItemImporter;
        $importer->importFromFile($this->createTempXmlFile($xml));

        // Assert: Item was created
        $item = Item::where('slug', 'leather-armor')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->strength_requirement);

        // Assert: No prerequisite was created
        $prerequisiteCount = EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item->id)
            ->count();

        $this->assertEquals(0, $prerequisiteCount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_prerequisites_on_reimport()
    {
        // Arrange: Create item with strength 13
        $xml1 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Chain Mail</name>
        <type>HA</type>
        <ac>16</ac>
        <strength>13</strength>
        <text>Source: Player's Handbook p. 145</text>
    </item>
</compendium>
XML;

        $importer = new ItemImporter;
        $importer->importFromFile($this->createTempXmlFile($xml1));

        $item = Item::where('slug', 'chain-mail')->first();
        $this->assertNotNull($item);

        // Verify initial prerequisite
        $prerequisite1 = EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item->id)
            ->first();
        $this->assertEquals(13, $prerequisite1->minimum_value);

        // Act: Reimport with strength 15
        $xml2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Chain Mail</name>
        <type>HA</type>
        <ac>16</ac>
        <strength>15</strength>
        <text>Source: Player's Handbook p. 145</text>
    </item>
</compendium>
XML;

        $importer->importFromFile($this->createTempXmlFile($xml2));

        // Assert: Only one prerequisite exists with updated value
        $prerequisites = EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item->id)
            ->get();

        $this->assertCount(1, $prerequisites);
        $this->assertEquals(15, $prerequisites->first()->minimum_value);

        $item->refresh();
        $this->assertEquals(15, $item->strength_requirement);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_removes_prerequisites_when_strength_requirement_removed()
    {
        // Arrange: Create item with strength requirement
        $xml1 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Splint Armor</name>
        <type>HA</type>
        <ac>17</ac>
        <strength>15</strength>
        <text>Source: Player's Handbook p. 145</text>
    </item>
</compendium>
XML;

        $importer = new ItemImporter;
        $importer->importFromFile($this->createTempXmlFile($xml1));

        $item = Item::where('slug', 'splint-armor')->first();
        $this->assertNotNull($item);
        $this->assertEquals(1, $item->prerequisites()->count());

        // Act: Reimport without strength requirement
        $xml2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Splint Armor</name>
        <type>HA</type>
        <ac>17</ac>
        <text>Source: Player's Handbook p. 145</text>
    </item>
</compendium>
XML;

        $importer->importFromFile($this->createTempXmlFile($xml2));

        // Assert: Prerequisites were removed
        $item->refresh();
        $this->assertNull($item->strength_requirement);
        $this->assertEquals(0, $item->prerequisites()->count());
    }

    /**
     * Create a temporary XML file for testing.
     */
    private function createTempXmlFile(string $xml): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_item_').'.xml';
        file_put_contents($tmpFile, $xml);

        return $tmpFile;
    }
}
