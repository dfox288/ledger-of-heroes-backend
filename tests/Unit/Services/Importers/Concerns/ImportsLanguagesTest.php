<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\Background;
use App\Models\EntityLanguage;
use App\Models\Language;
use App\Models\Race;
use App\Services\Importers\Concerns\ImportsLanguages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ImportsLanguagesTest extends TestCase
{
    use ImportsLanguages;
    use RefreshDatabase;

    #[Test]
    public function it_imports_fixed_language_by_slug()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'slug' => 'common',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(1, $race->languages);
        $language = $race->languages->first();
        $this->assertEquals(Language::where('slug', 'common')->first()->id, $language->language_id);
        $this->assertFalse($language->is_choice);
    }

    #[Test]
    public function it_imports_fixed_language_by_id()
    {
        $race = Race::factory()->create();
        $dwarvish = Language::where('slug', 'dwarvish')->first();

        $languagesData = [
            [
                'language_id' => $dwarvish->id,
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(1, $race->languages);
        $this->assertEquals($dwarvish->id, $race->languages->first()->language_id);
    }

    #[Test]
    public function it_imports_language_choice_slot()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'is_choice' => true,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(1, $race->languages);
        $language = $race->languages->first();
        $this->assertNull($language->language_id);
        $this->assertTrue($language->is_choice);
    }

    #[Test]
    public function it_imports_multiple_languages_mixed()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'slug' => 'common',
                'is_choice' => false,
            ],
            [
                'slug' => 'elvish',
                'is_choice' => false,
            ],
            [
                'is_choice' => true, // One extra language of choice
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(3, $race->languages);

        $fixed = $race->languages->where('is_choice', false);
        $choices = $race->languages->where('is_choice', true);

        $this->assertCount(2, $fixed);
        $this->assertCount(1, $choices);
    }

    #[Test]
    public function it_clears_existing_languages_before_import()
    {
        $race = Race::factory()->create();

        // Create initial languages
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => Language::where('slug', 'common')->first()->id,
            'is_choice' => false,
        ]);

        $this->assertCount(1, $race->fresh()->languages);

        // Import new languages (should clear old ones)
        $languagesData = [
            [
                'slug' => 'dwarvish',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $race->refresh();
        $this->assertCount(1, $race->languages);
        $this->assertEquals(
            Language::where('slug', 'dwarvish')->first()->id,
            $race->languages->first()->language_id
        );
    }

    #[Test]
    public function it_skips_languages_when_slug_lookup_fails()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'slug' => 'nonexistent-language',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(0, $race->languages);
    }

    #[Test]
    public function it_handles_empty_languages_array()
    {
        $race = Race::factory()->create();

        // Create initial language
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => Language::where('slug', 'common')->first()->id,
            'is_choice' => false,
        ]);

        $this->assertCount(1, $race->fresh()->languages);

        // Import empty array (should clear all)
        $this->importEntityLanguages($race, []);

        $this->assertCount(0, $race->fresh()->languages);
    }

    #[Test]
    public function it_works_with_backgrounds()
    {
        $background = Background::factory()->create();

        $languagesData = [
            [
                'slug' => 'common',
                'is_choice' => false,
            ],
            [
                'is_choice' => true,
            ],
        ];

        $this->importEntityLanguages($background, $languagesData);

        $this->assertCount(2, $background->languages);
    }

    #[Test]
    public function it_prefers_language_id_over_slug_when_both_provided()
    {
        $race = Race::factory()->create();
        $elvish = Language::where('slug', 'elvish')->first();

        $languagesData = [
            [
                'language_id' => $elvish->id,
                'slug' => 'common', // Should be ignored
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertEquals($elvish->id, $race->languages->first()->language_id);
    }

    #[Test]
    public function it_imports_multiple_choice_slots()
    {
        $race = Race::factory()->create();

        $languagesData = [
            ['is_choice' => true],
            ['is_choice' => true],
            ['is_choice' => true],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(3, $race->languages);
        $this->assertTrue($race->languages->every(fn ($l) => $l->is_choice));
        $this->assertTrue($race->languages->every(fn ($l) => $l->language_id === null));
    }
}
