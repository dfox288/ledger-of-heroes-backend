<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\EntityLanguage;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ClassLanguageApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function rogue_class_has_thieves_cant_language(): void
    {
        // Create rogue with Thieves' Cant language
        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
        ]);

        $thievesCant = Language::where('slug', 'thieves-cant')->first();
        $this->assertNotNull($thievesCant, "Thieves' Cant should exist in seeded languages");

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $rogue->id,
            'language_id' => $thievesCant->id,
            'is_choice' => false,
        ]);

        // Verify the relationship works
        $rogue->refresh();
        $this->assertCount(1, $rogue->languages);
        $this->assertEquals($thievesCant->id, $rogue->languages->first()->language_id);

        // Verify API response includes languages
        $response = $this->getJson("/api/v1/classes/{$rogue->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'languages' => [
                        '*' => ['language', 'is_choice'],
                    ],
                ],
            ]);

        // Verify the language data
        $languages = $response->json('data.languages');
        $this->assertCount(1, $languages);
        $this->assertFalse($languages[0]['is_choice']);
        $this->assertEquals("Thieves' Cant", $languages[0]['language']['name']);
    }

    #[Test]
    public function druid_class_has_druidic_language(): void
    {
        // Create druid with Druidic language
        $druid = CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'druid',
        ]);

        $druidic = Language::where('slug', 'druidic')->first();
        $this->assertNotNull($druidic, 'Druidic should exist in seeded languages');

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $druid->id,
            'language_id' => $druidic->id,
            'is_choice' => false,
        ]);

        // Verify the relationship works
        $druid->refresh();
        $this->assertCount(1, $druid->languages);

        // Verify API response
        $response = $this->getJson("/api/v1/classes/{$druid->id}");

        $response->assertOk();
        $languages = $response->json('data.languages');
        $this->assertCount(1, $languages);
        $this->assertEquals('Druidic', $languages[0]['language']['name']);
    }

    #[Test]
    public function class_without_language_grants_has_empty_languages(): void
    {
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        // No languages added

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertOk();
        $this->assertEmpty($response->json('data.languages'));
    }

    #[Test]
    public function character_class_model_has_languages_relationship(): void
    {
        $class = CharacterClass::factory()->create();
        $language = Language::first();

        EntityLanguage::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'language_id' => $language->id,
            'is_choice' => false,
        ]);

        $class->refresh();

        $this->assertTrue(method_exists($class, 'languages'));
        $this->assertCount(1, $class->languages);
        $this->assertEquals($language->id, $class->languages->first()->language_id);
    }
}
