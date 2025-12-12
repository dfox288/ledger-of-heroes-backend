<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\Background;
use App\Models\EntityChoice;
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
                'slug' => 'core:common',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertCount(1, $race->languages);
        $language = $race->languages->first();
        $this->assertEquals(Language::where('slug', 'core:common')->first()->id, $language->language_id);
    }

    #[Test]
    public function it_imports_fixed_language_by_id()
    {
        $race = Race::factory()->create();
        $dwarvish = Language::where('slug', 'core:dwarvish')->first();

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

        // No fixed languages
        $this->assertCount(0, $race->languages);

        // One language choice
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(1, $choices);
        $choice = $choices->first();
        $this->assertEquals('language_choice_1', $choice->choice_group);
        $this->assertEquals(1, $choice->quantity);
    }

    #[Test]
    public function it_imports_multiple_languages_mixed()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'slug' => 'core:common',
                'is_choice' => false,
            ],
            [
                'slug' => 'core:elvish',
                'is_choice' => false,
            ],
            [
                'is_choice' => true, // One extra language of choice
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // Two fixed languages
        $this->assertCount(2, $race->languages);

        // One language choice
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

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
            'language_id' => Language::where('slug', 'core:common')->first()->id,
        ]);

        $this->assertCount(1, $race->fresh()->languages);

        // Import new languages (should clear old ones)
        $languagesData = [
            [
                'slug' => 'core:dwarvish',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $race->refresh();
        $this->assertCount(1, $race->languages);
        $this->assertEquals(
            Language::where('slug', 'core:dwarvish')->first()->id,
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
            'language_id' => Language::where('slug', 'core:common')->first()->id,
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
                'slug' => 'core:common',
                'is_choice' => false,
            ],
            [
                'is_choice' => true,
            ],
        ];

        $this->importEntityLanguages($background, $languagesData);

        // One fixed language
        $this->assertCount(1, $background->languages);

        // One language choice
        $choices = EntityChoice::where('reference_type', Background::class)
            ->where('reference_id', $background->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(1, $choices);
    }

    #[Test]
    public function it_prefers_slug_over_language_id_when_both_provided()
    {
        $race = Race::factory()->create();
        $common = Language::where('slug', 'core:common')->first();

        // The trait priority is: slug > language_slug > language_id
        $languagesData = [
            [
                'language_id' => 99999, // Should be ignored in favor of slug
                'slug' => 'core:common',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $this->assertEquals($common->id, $race->languages->first()->language_id);
    }

    #[Test]
    public function it_imports_multiple_choice_slots()
    {
        $race = Race::factory()->create();

        $languagesData = [
            ['is_choice' => true, 'quantity' => 3],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // No fixed languages
        $this->assertCount(0, $race->languages);

        // Three language choice records (one per slot)
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(3, $choices);
        $this->assertEquals('language_choice_1', $choices[0]->choice_group);
        $this->assertEquals('language_choice_2', $choices[1]->choice_group);
        $this->assertEquals('language_choice_3', $choices[2]->choice_group);
    }

    #[Test]
    public function it_imports_restricted_choice_with_choice_group()
    {
        $race = Race::factory()->create();
        $dwarvish = Language::where('slug', 'core:dwarvish')->first();
        $elvish = Language::where('slug', 'core:elvish')->first();

        $languagesData = [
            [
                'language_id' => $dwarvish->id,
                'is_choice' => true,
                'choice_group' => 'race_language_choice',
                'choice_option' => 1,
                'quantity' => 1,
            ],
            [
                'language_id' => $elvish->id,
                'is_choice' => true,
                'choice_group' => 'race_language_choice',
                'choice_option' => 2,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // No fixed languages
        $this->assertCount(0, $race->languages);

        // Two restricted language choice options
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->where('choice_group', 'race_language_choice')
            ->get();

        $this->assertCount(2, $choices);

        $firstChoice = $choices->where('choice_option', 1)->first();
        $this->assertEquals('core:dwarvish', $firstChoice->target_slug);
        $this->assertEquals('language', $firstChoice->target_type);
        $this->assertEquals(1, $firstChoice->quantity);

        $secondChoice = $choices->where('choice_option', 2)->first();
        $this->assertEquals('core:elvish', $secondChoice->target_slug);
    }

    #[Test]
    public function it_imports_unrestricted_choice_with_quantity()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'is_choice' => true,
                'quantity' => 2,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // Two language choice records (one per slot)
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(2, $choices);
        // Each slot has quantity 1 (not quantity 2 per slot)
        $this->assertTrue($choices->every(fn ($c) => $c->quantity === 1));
        // No target restriction
        $this->assertTrue($choices->every(fn ($c) => $c->target_type === null));
        $this->assertTrue($choices->every(fn ($c) => $c->target_slug === null));
    }

    #[Test]
    public function it_imports_conditional_language_choice_with_condition_type()
    {
        $race = Race::factory()->create();
        $dwarvish = Language::where('slug', 'core:dwarvish')->first();

        $languagesData = [
            [
                'is_choice' => true,
                'quantity' => 1,
                'condition_type' => 'unless_already_knows',
                'condition_language_id' => $dwarvish->id,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $choice = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->first();

        $this->assertNotNull($choice);
        $this->assertEquals('unless_already_knows', $choice->constraints['condition_type'] ?? null);
        $this->assertEquals('core:dwarvish', $choice->constraints['condition_language_slug'] ?? null);
    }

    #[Test]
    public function it_resolves_condition_language_slug_to_id()
    {
        $race = Race::factory()->create();

        $languagesData = [
            [
                'is_choice' => true,
                'quantity' => 1,
                'condition_type' => 'unless_already_knows',
                'condition_language_slug' => 'core:dwarvish',
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $choice = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->first();

        $this->assertNotNull($choice);
        $this->assertEquals('core:dwarvish', $choice->constraints['condition_language_slug'] ?? null);
    }

    #[Test]
    public function it_prefers_condition_language_id_over_slug()
    {
        $race = Race::factory()->create();
        $dwarvish = Language::where('slug', 'core:dwarvish')->first();

        $languagesData = [
            [
                'is_choice' => true,
                'quantity' => 1,
                'condition_type' => 'unless_already_knows',
                'condition_language_id' => $dwarvish->id,
                'condition_language_slug' => 'core:elvish', // Should be ignored
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $choice = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->first();

        $this->assertNotNull($choice);
        // The trait resolves condition_language_id to slug, so it should be dwarvish
        $this->assertEquals('core:dwarvish', $choice->constraints['condition_language_slug'] ?? null);
    }

    #[Test]
    public function it_creates_restricted_choice_even_with_unresolvable_language()
    {
        $race = Race::factory()->create();

        // The trait stores the slug as-is without validation
        // Validation should happen at a higher level (parser or API)
        $languagesData = [
            [
                'is_choice' => true,
                'choice_group' => 'race_choice',
                'choice_option' => 1,
                'language_slug' => 'nonexistent-language',
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // The EntityChoice is created with the provided slug (no validation)
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(1, $choices);
        $this->assertEquals('nonexistent-language', $choices->first()->target_slug);
    }

    #[Test]
    public function it_handles_language_slug_format()
    {
        $race = Race::factory()->create();

        // Test legacy 'slug' format (should still work)
        $languagesData = [
            [
                'slug' => 'core:common',
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $common = Language::where('slug', 'core:common')->first();
        $this->assertEquals($common->id, $race->languages->first()->language_id);
    }

    #[Test]
    public function it_handles_language_slug_priority()
    {
        $race = Race::factory()->create();
        $elvish = Language::where('slug', 'core:elvish')->first();

        // Priority is: slug > language_slug > language_id
        $languagesData = [
            [
                'language_id' => 99999, // Lowest priority - ignored
                'language_slug' => 'core:common', // Middle priority - ignored
                'slug' => 'core:elvish', // Highest priority - used
                'is_choice' => false,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // Should use slug (highest priority)
        $this->assertEquals($elvish->id, $race->languages->first()->language_id);
    }

    #[Test]
    public function it_handles_mixed_fixed_unrestricted_and_restricted_choices()
    {
        $race = Race::factory()->create();
        $common = Language::where('slug', 'core:common')->first();
        $dwarvish = Language::where('slug', 'core:dwarvish')->first();
        $elvish = Language::where('slug', 'core:elvish')->first();

        $languagesData = [
            // Fixed language
            [
                'language_id' => $common->id,
                'is_choice' => false,
            ],
            // Unrestricted choice
            [
                'is_choice' => true,
                'quantity' => 1,
            ],
            // Restricted choice group
            [
                'language_id' => $dwarvish->id,
                'is_choice' => true,
                'choice_group' => 'subrace_choice',
                'choice_option' => 1,
            ],
            [
                'language_id' => $elvish->id,
                'is_choice' => true,
                'choice_group' => 'subrace_choice',
                'choice_option' => 2,
            ],
        ];

        $this->importEntityLanguages($race, $languagesData);

        // Verify fixed language
        $this->assertCount(1, $race->languages);
        $this->assertEquals($common->id, $race->languages->first()->language_id);

        // Verify choices (1 unrestricted + 2 restricted)
        $choices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(3, $choices);

        // Verify unrestricted choice
        $unrestricted = $choices->where('choice_group', 'language_choice_1')->first();
        $this->assertNotNull($unrestricted);
        $this->assertNull($unrestricted->target_type);

        // Verify restricted choices
        $restricted = $choices->where('choice_group', 'subrace_choice');
        $this->assertCount(2, $restricted);
    }

    #[Test]
    public function it_clears_existing_language_choices_before_import()
    {
        $race = Race::factory()->create();

        // Create initial language choice
        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'language',
            'choice_group' => 'old_choice',
            'quantity' => 1,
            'level_granted' => 1,
            'is_required' => true,
        ]);

        $oldChoices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        $this->assertCount(1, $oldChoices);

        // Import new choices (should clear old ones)
        $languagesData = [
            ['is_choice' => true, 'quantity' => 2],
        ];

        $this->importEntityLanguages($race, $languagesData);

        $newChoices = EntityChoice::where('reference_type', Race::class)
            ->where('reference_id', $race->id)
            ->where('choice_type', 'language')
            ->get();

        // Old choice should be gone, new choices should be present
        $this->assertCount(2, $newChoices);
        $this->assertNull($newChoices->where('choice_group', 'old_choice')->first());
    }
}
