<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Services\CharacterExportService;
use App\Services\CharacterImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character physical attributes and deity fields.
 *
 * Covers issue #758 - Add physical description and deity fields to Character.
 *
 * New fields: age, height, weight, eye_color, hair_color, skin_color, deity
 */
class CharacterPhysicalAttributesTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Create Tests
    // =====================

    #[Test]
    public function it_can_create_character_with_physical_attributes(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'brave-hero-ab12',
            'name' => 'Gandalf',
            'age' => '2019',
            'height' => "5'10\"",
            'weight' => '180 lbs',
            'eye_color' => 'Grey',
            'hair_color' => 'Grey',
            'skin_color' => 'Fair',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.age', '2019')
            ->assertJsonPath('data.height', "5'10\"")
            ->assertJsonPath('data.weight', '180 lbs')
            ->assertJsonPath('data.eye_color', 'Grey')
            ->assertJsonPath('data.hair_color', 'Grey')
            ->assertJsonPath('data.skin_color', 'Fair');

        $this->assertDatabaseHas('characters', [
            'name' => 'Gandalf',
            'age' => '2019',
            'height' => "5'10\"",
            'weight' => '180 lbs',
            'eye_color' => 'Grey',
            'hair_color' => 'Grey',
            'skin_color' => 'Fair',
        ]);
    }

    #[Test]
    public function it_can_create_character_with_deity(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'pious-cleric-cd34',
            'name' => 'Cleric Pete',
            'deity' => 'Pelor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.deity', 'Pelor');

        $this->assertDatabaseHas('characters', [
            'name' => 'Cleric Pete',
            'deity' => 'Pelor',
        ]);
    }

    #[Test]
    public function it_defaults_physical_attributes_to_null(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'simple-char-ef56',
            'name' => 'Simple Character',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.height', null)
            ->assertJsonPath('data.weight', null)
            ->assertJsonPath('data.eye_color', null)
            ->assertJsonPath('data.hair_color', null)
            ->assertJsonPath('data.skin_color', null)
            ->assertJsonPath('data.deity', null);
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_can_update_character_physical_attributes(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'age' => '25',
            'height' => '6\'2"',
            'weight' => '200 lbs',
            'eye_color' => 'Blue',
            'hair_color' => 'Blonde',
            'skin_color' => 'Tan',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.age', '25')
            ->assertJsonPath('data.height', '6\'2"')
            ->assertJsonPath('data.weight', '200 lbs')
            ->assertJsonPath('data.eye_color', 'Blue')
            ->assertJsonPath('data.hair_color', 'Blonde')
            ->assertJsonPath('data.skin_color', 'Tan');

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'age' => '25',
            'height' => '6\'2"',
            'weight' => '200 lbs',
            'eye_color' => 'Blue',
            'hair_color' => 'Blonde',
            'skin_color' => 'Tan',
        ]);
    }

    #[Test]
    public function it_can_update_character_deity(): void
    {
        $character = Character::factory()->create(['deity' => 'Pelor']);

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'deity' => 'Bahamut',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deity', 'Bahamut');

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'deity' => 'Bahamut',
        ]);
    }

    #[Test]
    public function it_can_clear_physical_attributes(): void
    {
        $character = Character::factory()->create([
            'age' => '25',
            'height' => '6\'0"',
            'deity' => 'Pelor',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'age' => null,
            'height' => null,
            'deity' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.height', null)
            ->assertJsonPath('data.deity', null);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'age' => null,
            'height' => null,
            'deity' => null,
        ]);
    }

    // =====================
    // Show Tests
    // =====================

    #[Test]
    public function it_returns_physical_attributes_in_show_response(): void
    {
        $character = Character::factory()->create([
            'age' => '30',
            'height' => '5\'8"',
            'weight' => '160 lbs',
            'eye_color' => 'Brown',
            'hair_color' => 'Black',
            'skin_color' => 'Olive',
            'deity' => 'Moradin',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.age', '30')
            ->assertJsonPath('data.height', '5\'8"')
            ->assertJsonPath('data.weight', '160 lbs')
            ->assertJsonPath('data.eye_color', 'Brown')
            ->assertJsonPath('data.hair_color', 'Black')
            ->assertJsonPath('data.skin_color', 'Olive')
            ->assertJsonPath('data.deity', 'Moradin');
    }

    // =====================
    // Validation Tests
    // =====================

    #[Test]
    public function it_validates_age_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-gh78',
            'name' => 'Test',
            'age' => str_repeat('x', 51), // 51 chars, max is 50
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['age']);
    }

    #[Test]
    public function it_validates_height_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-ij90',
            'name' => 'Test',
            'height' => str_repeat('x', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['height']);
    }

    #[Test]
    public function it_validates_weight_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-kl12',
            'name' => 'Test',
            'weight' => str_repeat('x', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    #[Test]
    public function it_validates_eye_color_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-mn34',
            'name' => 'Test',
            'eye_color' => str_repeat('x', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['eye_color']);
    }

    #[Test]
    public function it_validates_hair_color_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-op56',
            'name' => 'Test',
            'hair_color' => str_repeat('x', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hair_color']);
    }

    #[Test]
    public function it_validates_skin_color_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-qr78',
            'name' => 'Test',
            'skin_color' => str_repeat('x', 51),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['skin_color']);
    }

    #[Test]
    public function it_validates_deity_max_length(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-st90',
            'name' => 'Test',
            'deity' => str_repeat('x', 151), // 151 chars, max is 150
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['deity']);
    }

    // =====================
    // Export Tests
    // =====================

    #[Test]
    public function it_exports_physical_attributes(): void
    {
        $character = Character::factory()->create([
            'age' => '100',
            'height' => '4\'0"',
            'weight' => '120 lbs',
            'eye_color' => 'Green',
            'hair_color' => 'Red',
            'skin_color' => 'Pale',
            'deity' => 'Corellon',
        ]);

        $service = app(CharacterExportService::class);
        $exported = $service->export($character);

        $this->assertEquals('100', $exported['character']['age']);
        $this->assertEquals('4\'0"', $exported['character']['height']);
        $this->assertEquals('120 lbs', $exported['character']['weight']);
        $this->assertEquals('Green', $exported['character']['eye_color']);
        $this->assertEquals('Red', $exported['character']['hair_color']);
        $this->assertEquals('Pale', $exported['character']['skin_color']);
        $this->assertEquals('Corellon', $exported['character']['deity']);
    }

    #[Test]
    public function it_exports_null_physical_attributes_when_not_set(): void
    {
        $character = Character::factory()->create();

        $service = app(CharacterExportService::class);
        $exported = $service->export($character);

        $this->assertNull($exported['character']['age']);
        $this->assertNull($exported['character']['height']);
        $this->assertNull($exported['character']['weight']);
        $this->assertNull($exported['character']['eye_color']);
        $this->assertNull($exported['character']['hair_color']);
        $this->assertNull($exported['character']['skin_color']);
        $this->assertNull($exported['character']['deity']);
    }

    // =====================
    // Import Tests
    // =====================

    #[Test]
    public function it_imports_physical_attributes(): void
    {
        $exportData = [
            'format_version' => '1.3',
            'exported_at' => now()->toIso8601String(),
            'character' => [
                'public_id' => 'import-test-mn34',
                'name' => 'Imported Character',
                'race' => null,
                'background' => null,
                'alignment' => null,
                'ability_scores' => [],
                'age' => '50',
                'height' => '5\'5"',
                'weight' => '140 lbs',
                'eye_color' => 'Amber',
                'hair_color' => 'Silver',
                'skin_color' => 'Bronze',
                'deity' => 'Lathander',
                'classes' => [],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
                'ability_score_choices' => [],
                'spell_slots' => [],
                'features' => [],
                'counters' => [],
                'portrait' => null,
            ],
        ];

        $service = app(CharacterImportService::class);
        $result = $service->import($exportData);

        $character = $result->character;
        $this->assertEquals('50', $character->age);
        $this->assertEquals('5\'5"', $character->height);
        $this->assertEquals('140 lbs', $character->weight);
        $this->assertEquals('Amber', $character->eye_color);
        $this->assertEquals('Silver', $character->hair_color);
        $this->assertEquals('Bronze', $character->skin_color);
        $this->assertEquals('Lathander', $character->deity);
    }

    #[Test]
    public function it_imports_null_physical_attributes(): void
    {
        $exportData = [
            'format_version' => '1.3',
            'exported_at' => now()->toIso8601String(),
            'character' => [
                'public_id' => 'import-test-op56',
                'name' => 'Minimal Character',
                'race' => null,
                'background' => null,
                'alignment' => null,
                'ability_scores' => [],
                // Physical attributes intentionally omitted
                'classes' => [],
                'spells' => [],
                'equipment' => [],
                'languages' => [],
                'proficiencies' => ['skills' => [], 'types' => []],
                'conditions' => [],
                'feature_selections' => [],
                'notes' => [],
                'ability_score_choices' => [],
                'spell_slots' => [],
                'features' => [],
                'counters' => [],
                'portrait' => null,
            ],
        ];

        $service = app(CharacterImportService::class);
        $result = $service->import($exportData);

        $character = $result->character;
        $this->assertNull($character->age);
        $this->assertNull($character->height);
        $this->assertNull($character->weight);
        $this->assertNull($character->eye_color);
        $this->assertNull($character->hair_color);
        $this->assertNull($character->skin_color);
        $this->assertNull($character->deity);
    }
}
