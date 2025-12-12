<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Language;
use App\Models\Race;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class LanguageReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_returns_races_for_language()
    {
        $elvish = Language::where('slug', 'elvish')->first() ?? Language::factory()->create(['name' => 'Elvish Test', 'slug' => 'elvish-test']);

        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $halfElf = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);

        $elvish->races()->attach($elf);
        $elvish->races()->attach($halfElf);

        // Different language - should not appear
        $draconic = Language::where('slug', 'draconic')->first() ?? Language::factory()->create(['name' => 'Draconic Test', 'slug' => 'draconic-test']);
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn']);
        $draconic->races()->attach($dragonborn);

        $this->assertReturnsRelatedEntities("/api/v1/lookups/languages/{$elvish->id}/races", 2, ['Elf', 'Half-Elf']);
    }

    #[Test]
    public function it_returns_empty_when_language_has_no_races()
    {
        $abyssal = Language::where('slug', 'abyssal')->first() ?? Language::factory()->create(['name' => 'Abyssal Test', 'slug' => 'abyssal-test']);

        $this->assertReturnsEmpty("/api/v1/lookups/languages/{$abyssal->id}/races");
    }

    #[Test]
    public function it_accepts_slug_for_races_endpoint()
    {
        $common = Language::where('slug', 'common')->first() ?? Language::factory()->create(['name' => 'Common Test', 'slug' => 'common-test']);

        $human = Race::factory()->create(['name' => 'Human']);
        $common->races()->attach($human);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/languages/{$common->slug}/races");
    }

    #[Test]
    public function it_paginates_race_results()
    {
        $undercommon = Language::where('slug', 'undercommon')->first() ?? Language::factory()->create(['name' => 'Undercommon Test', 'slug' => 'undercommon-test']);

        $this->createMultipleEntities(75, function () use ($undercommon) {
            $race = Race::factory()->create();
            $undercommon->races()->attach($race);

            return $race;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/languages/{$undercommon->id}/races?per_page=25", 25, 75, 25);
    }

    #[Test]
    public function it_returns_backgrounds_for_language()
    {
        $thieves = Language::where('slug', 'thieves-cant')->first() ?? Language::factory()->create(['name' => "Thieves' Cant Test", 'slug' => 'thieves-cant-test']);

        $criminal = Background::factory()->create(['name' => 'Criminal', 'slug' => 'criminal']);
        $urchin = Background::factory()->create(['name' => 'Urchin', 'slug' => 'urchin']);

        $thieves->backgrounds()->attach($criminal);
        $thieves->backgrounds()->attach($urchin);

        // Different language - should not appear
        $dwarvish = Language::where('slug', 'dwarvish')->first() ?? Language::factory()->create(['name' => 'Dwarvish Test', 'slug' => 'dwarvish-test']);
        $guildArtisan = Background::factory()->create(['name' => 'Guild Artisan']);
        $dwarvish->backgrounds()->attach($guildArtisan);

        $this->assertReturnsRelatedEntities("/api/v1/lookups/languages/{$thieves->id}/backgrounds", 2, ['Criminal', 'Urchin']);
    }

    #[Test]
    public function it_returns_empty_when_language_has_no_backgrounds()
    {
        $celestial = Language::where('slug', 'celestial')->first() ?? Language::factory()->create(['name' => 'Celestial Test', 'slug' => 'celestial-test']);

        $this->assertReturnsEmpty("/api/v1/lookups/languages/{$celestial->id}/backgrounds");
    }

    #[Test]
    public function it_accepts_slug_for_backgrounds_endpoint()
    {
        $primordial = Language::where('slug', 'primordial')->first() ?? Language::factory()->create(['name' => 'Primordial Test', 'slug' => 'primordial-test']);

        $outlander = Background::factory()->create(['name' => 'Outlander '.uniqid()]);
        $primordial->backgrounds()->attach($outlander);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/languages/{$primordial->slug}/backgrounds");
    }

    #[Test]
    public function it_paginates_background_results()
    {
        $giant = Language::where('slug', 'giant')->first() ?? Language::factory()->create(['name' => 'Giant Test', 'slug' => 'giant-test']);

        $this->createMultipleEntities(60, function () use ($giant) {
            $background = Background::factory()->create();
            $giant->backgrounds()->attach($background);

            return $background;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/languages/{$giant->id}/backgrounds?per_page=20", 20, 60, 20);
    }
}
