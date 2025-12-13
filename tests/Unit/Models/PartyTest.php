<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class PartyTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $party->user);
        $this->assertEquals($user->id, $party->user->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_belongs_to_many_characters(): void
    {
        $party = Party::factory()->create();
        $characters = Character::factory()->count(3)->create();

        $party->characters()->attach($characters);

        $this->assertCount(3, $party->characters);
        $this->assertInstanceOf(Character::class, $party->characters->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_timestamps(): void
    {
        $party = Party::factory()->create();

        $this->assertNotNull($party->created_at);
        $this->assertNotNull($party->updated_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_fillable_attributes(): void
    {
        $party = new Party;

        $this->assertContains('name', $party->getFillable());
        $this->assertContains('description', $party->getFillable());
        $this->assertContains('user_id', $party->getFillable());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function characters_pivot_includes_joined_at_and_display_order(): void
    {
        $party = Party::factory()->create();
        $character = Character::factory()->create();

        $party->characters()->attach($character, [
            'joined_at' => now(),
            'display_order' => 1,
        ]);

        $pivot = $party->characters->first()->pivot;

        $this->assertNotNull($pivot->joined_at);
        $this->assertEquals(1, $pivot->display_order);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function character_can_be_in_multiple_parties(): void
    {
        $character = Character::factory()->create();
        $party1 = Party::factory()->create();
        $party2 = Party::factory()->create();

        $party1->characters()->attach($character);
        $party2->characters()->attach($character);

        $this->assertCount(1, $party1->characters);
        $this->assertCount(1, $party2->characters);
        $this->assertCount(2, $character->parties);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_parties_for_a_character(): void
    {
        $character = Character::factory()->create();
        $party = Party::factory()->create();

        $party->characters()->attach($character);

        $this->assertInstanceOf(Party::class, $character->parties->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_duplicate_character_in_same_party(): void
    {
        $party = Party::factory()->create();
        $character = Character::factory()->create();

        $party->characters()->attach($character);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $party->characters()->attach($character);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cascades_delete_on_party_deletion(): void
    {
        $party = Party::factory()->create();
        $character = Character::factory()->create();
        $party->characters()->attach($character);

        $partyId = $party->id;
        $party->delete();

        $this->assertDatabaseMissing('party_characters', [
            'party_id' => $partyId,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cascades_delete_on_character_deletion(): void
    {
        $party = Party::factory()->create();
        $character = Character::factory()->create();
        $party->characters()->attach($character);

        $characterId = $character->id;
        $character->delete();

        $this->assertDatabaseMissing('party_characters', [
            'character_id' => $characterId,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pivot_includes_timestamps(): void
    {
        $party = Party::factory()->create();
        $character = Character::factory()->create();

        $party->characters()->attach($character);

        $pivot = $party->characters->first()->pivot;

        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }
}
