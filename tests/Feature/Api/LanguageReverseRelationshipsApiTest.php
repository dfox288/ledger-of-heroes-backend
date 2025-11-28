<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Language;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class LanguageReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_returns_races_for_language()
    {
        $elvish = Language::where('slug', 'elvish')->first() ?? Language::factory()->create([
            'name' => 'Elvish Test',
            'slug' => 'elvish-test',
        ]);

        $elf = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
        ]);

        $halfElf = Race::factory()->create([
            'name' => 'Half-Elf',
            'slug' => 'half-elf',
        ]);

        // Attach races to language via entity_languages (polymorphic)
        $elvish->races()->attach($elf, ['is_choice' => false]);
        $elvish->races()->attach($halfElf, ['is_choice' => false]);

        // Different language - should not appear
        $draconic = Language::where('slug', 'draconic')->first() ?? Language::factory()->create([
            'name' => 'Draconic Test',
            'slug' => 'draconic-test',
        ]);
        $dragonborn = Race::factory()->create(['name' => 'Dragonborn']);
        $draconic->races()->attach($dragonborn, ['is_choice' => false]);

        $response = $this->getJson("/api/v1/lookups/languages/{$elvish->id}/races");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Elf')
            ->assertJsonPath('data.1.name', 'Half-Elf');
    }

    #[Test]
    public function it_returns_empty_when_language_has_no_races()
    {
        $abyssal = Language::where('slug', 'abyssal')->first() ?? Language::factory()->create([
            'name' => 'Abyssal Test',
            'slug' => 'abyssal-test',
        ]);

        $response = $this->getJson("/api/v1/lookups/languages/{$abyssal->id}/races");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_slug_for_races_endpoint()
    {
        $common = Language::where('slug', 'common')->first() ?? Language::factory()->create([
            'name' => 'Common Test',
            'slug' => 'common-test',
        ]);

        $human = Race::factory()->create(['name' => 'Human']);
        $common->races()->attach($human, ['is_choice' => false]);

        $response = $this->getJson("/api/v1/lookups/languages/{$common->slug}/races");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_race_results()
    {
        $undercommon = Language::where('slug', 'undercommon')->first() ?? Language::factory()->create([
            'name' => 'Undercommon Test',
            'slug' => 'undercommon-test',
        ]);

        // Create 75 races with Undercommon
        Race::factory()->count(75)->create()->each(function ($race) use ($undercommon) {
            $undercommon->races()->attach($race, ['is_choice' => false]);
        });

        $response = $this->getJson("/api/v1/lookups/languages/{$undercommon->id}/races?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.current_page', 1);
    }

    #[Test]
    public function it_returns_backgrounds_for_language()
    {
        $thieves = Language::where('slug', 'thieves-cant')->first() ?? Language::factory()->create([
            'name' => "Thieves' Cant Test",
            'slug' => 'thieves-cant-test',
        ]);

        $criminal = Background::factory()->create([
            'name' => 'Criminal',
            'slug' => 'criminal',
        ]);

        $urchin = Background::factory()->create([
            'name' => 'Urchin',
            'slug' => 'urchin',
        ]);

        // Attach backgrounds to language
        $thieves->backgrounds()->attach($criminal, ['is_choice' => false]);
        $thieves->backgrounds()->attach($urchin, ['is_choice' => false]);

        // Different language - should not appear
        $dwarvish = Language::where('slug', 'dwarvish')->first() ?? Language::factory()->create([
            'name' => 'Dwarvish Test',
            'slug' => 'dwarvish-test',
        ]);
        $guildArtisan = Background::factory()->create(['name' => 'Guild Artisan']);
        $dwarvish->backgrounds()->attach($guildArtisan, ['is_choice' => false]);

        $response = $this->getJson("/api/v1/lookups/languages/{$thieves->id}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Criminal')
            ->assertJsonPath('data.1.name', 'Urchin');
    }

    #[Test]
    public function it_returns_empty_when_language_has_no_backgrounds()
    {
        $celestial = Language::where('slug', 'celestial')->first() ?? Language::factory()->create([
            'name' => 'Celestial Test',
            'slug' => 'celestial-test',
        ]);

        $response = $this->getJson("/api/v1/lookups/languages/{$celestial->id}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_slug_for_backgrounds_endpoint()
    {
        $primordial = Language::where('slug', 'primordial')->first() ?? Language::factory()->create([
            'name' => 'Primordial Test',
            'slug' => 'primordial-test',
        ]);

        $outlander = Background::factory()->create(['name' => 'Outlander']);
        $primordial->backgrounds()->attach($outlander, ['is_choice' => false]);

        $response = $this->getJson("/api/v1/lookups/languages/{$primordial->slug}/backgrounds");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_background_results()
    {
        $giant = Language::where('slug', 'giant')->first() ?? Language::factory()->create([
            'name' => 'Giant Test',
            'slug' => 'giant-test',
        ]);

        // Create 60 backgrounds with Giant language
        Background::factory()->count(60)->create()->each(function ($background) use ($giant) {
            $giant->backgrounds()->attach($background, ['is_choice' => false]);
        });

        $response = $this->getJson("/api/v1/lookups/languages/{$giant->id}/backgrounds?per_page=20");

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 60)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.current_page', 1);
    }
}
