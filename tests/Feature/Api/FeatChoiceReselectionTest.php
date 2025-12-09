<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Issue #401: Re-selecting feat choice fails
 *
 * When a user tries to change their feat selection (e.g., switching from Alert to Athlete),
 * the backend should allow replacing the existing choice rather than throwing
 * "No remaining feat choices from race".
 */
class FeatChoiceReselectionTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private Race $variantHuman;

    private Feat $alertFeat;

    private Feat $athleteFeat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        // Create Variant Human race with bonus feat modifier
        $this->variantHuman = Race::factory()->create([
            'name' => 'Variant Human',
            'slug' => 'variant-human',
            'full_slug' => 'phb:variant-human',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $this->variantHuman->id,
            'modifier_category' => 'bonus_feat',
            'value' => '1',
        ]);

        // Create feats
        $this->alertFeat = Feat::factory()->create([
            'name' => 'Alert',
            'slug' => 'alert',
            'full_slug' => 'phb:alert',
        ]);

        $this->athleteFeat = Feat::factory()->create([
            'name' => 'Athlete',
            'slug' => 'athlete',
            'full_slug' => 'phb:athlete',
        ]);

        // Create character with Variant Human race
        $this->character = Character::factory()->create([
            'race_slug' => $this->variantHuman->full_slug,
        ]);
    }

    #[Test]
    public function it_allows_reselecting_feat_choice(): void
    {
        $choiceId = 'feat|race|phb:variant-human|1|bonus_feat';

        // First, select Alert feat
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:alert',
        ]);

        $response->assertOk();

        // Verify Alert was selected
        $this->assertDatabaseHas('character_features', [
            'character_id' => $this->character->id,
            'feature_type' => Feat::class,
            'feature_slug' => 'phb:alert',
            'source' => 'race',
        ]);

        // Now try to change to Athlete - this should work (replace the old selection)
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:athlete',
        ]);

        // This is the bug - it currently returns 422, but it should succeed
        $response->assertOk();

        // Verify Athlete is now selected instead of Alert
        $this->assertDatabaseHas('character_features', [
            'character_id' => $this->character->id,
            'feature_type' => Feat::class,
            'feature_slug' => 'phb:athlete',
            'source' => 'race',
        ]);

        // Verify Alert is no longer selected
        $this->assertDatabaseMissing('character_features', [
            'character_id' => $this->character->id,
            'feature_type' => Feat::class,
            'feature_slug' => 'phb:alert',
            'source' => 'race',
        ]);

        // Verify only one feat is selected (not both)
        $featCount = CharacterFeature::where('character_id', $this->character->id)
            ->where('feature_type', Feat::class)
            ->where('source', 'race')
            ->count();

        $this->assertEquals(1, $featCount);
    }

    #[Test]
    public function it_removes_old_feat_benefits_when_reselecting(): void
    {
        $choiceId = 'feat|race|phb:variant-human|1|bonus_feat';

        // Create a feat with ability score increases
        $durable = Feat::factory()->create([
            'name' => 'Durable',
            'slug' => 'durable',
            'full_slug' => 'phb:durable',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $durable->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => 3, // Constitution
            'value' => '1',
        ]);

        // Set initial Constitution score
        $this->character->update(['constitution' => 10]);

        // Select Durable feat (grants +1 CON)
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:durable',
        ]);

        $response->assertOk();

        // Verify CON was increased to 11
        $this->character->refresh();
        $this->assertEquals(11, $this->character->constitution);

        // Now change to Alert (no ability score increases)
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:alert',
        ]);

        $response->assertOk();

        // Verify CON was reduced back to 10
        $this->character->refresh();
        $this->assertEquals(10, $this->character->constitution);
    }

    #[Test]
    public function it_still_prevents_selecting_same_feat_twice(): void
    {
        $choiceId = 'feat|race|phb:variant-human|1|bonus_feat';

        // Select Alert from race
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:alert',
        ]);

        $response->assertOk();

        // Try to "replace" with Alert again (same feat) - should fail with "already taken"
        $response = $this->postJson("/api/v1/characters/{$this->character->id}/choices/{$choiceId}", [
            'feat' => 'phb:alert',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Character has already taken this feat.');
    }
}
