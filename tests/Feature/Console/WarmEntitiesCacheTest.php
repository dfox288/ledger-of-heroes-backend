<?php

namespace Tests\Feature\Console;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class WarmEntitiesCacheTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Automatically seed database

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_warms_all_entities_by_default(): void
    {
        // Arrange: Create sample entities
        $spell = Spell::factory()->create();
        $item = Item::factory()->create();
        $monster = Monster::factory()->create();
        $class = CharacterClass::factory()->create();
        $race = Race::factory()->create();
        $background = Background::factory()->create();
        $feat = Feat::factory()->create();

        // Act: Run command without options
        $this->artisan('cache:warm-entities')
            ->assertExitCode(0);

        // Assert: Verify all entities are cached
        $this->assertTrue(Cache::has("entity:spell:{$spell->id}"));
        $this->assertTrue(Cache::has("entity:item:{$item->id}"));
        $this->assertTrue(Cache::has("entity:monster:{$monster->id}"));
        $this->assertTrue(Cache::has("entity:class:{$class->id}"));
        $this->assertTrue(Cache::has("entity:race:{$race->id}"));
        $this->assertTrue(Cache::has("entity:background:{$background->id}"));
        $this->assertTrue(Cache::has("entity:feat:{$feat->id}"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_warm_specific_entity_type(): void
    {
        // Arrange: Create sample entities
        $spell = Spell::factory()->create();
        $item = Item::factory()->create();

        // Act: Run command with --type=spell option
        $this->artisan('cache:warm-entities', ['--type' => ['spell']])
            ->assertExitCode(0);

        // Assert: Only spells are cached
        $this->assertTrue(Cache::has("entity:spell:{$spell->id}"));
        $this->assertFalse(Cache::has("entity:item:{$item->id}"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_warm_multiple_specific_entity_types(): void
    {
        // Arrange: Create sample entities
        $spell = Spell::factory()->create();
        $item = Item::factory()->create();
        $monster = Monster::factory()->create();

        // Act: Run command with multiple --type options
        $this->artisan('cache:warm-entities', ['--type' => ['spell', 'item']])
            ->assertExitCode(0);

        // Assert: Only spells and items are cached
        $this->assertTrue(Cache::has("entity:spell:{$spell->id}"));
        $this->assertTrue(Cache::has("entity:item:{$item->id}"));
        $this->assertFalse(Cache::has("entity:monster:{$monster->id}"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_outputs_success_messages(): void
    {
        // Arrange: Create sample entities
        Spell::factory()->count(3)->create();
        Item::factory()->count(2)->create();

        // Act & Assert: Run command and verify output
        $this->artisan('cache:warm-entities', ['--type' => ['spell', 'item']])
            ->expectsOutput('Warming entity caches...')
            ->expectsOutput('✓ spells cached (3 entities)')
            ->expectsOutput('✓ items cached (2 entities)')
            ->expectsOutput('All entity caches warmed successfully!')
            ->expectsOutput('Total: 5 entities cached with 15-minute TTL')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_database_gracefully(): void
    {
        // Act & Assert: Run command on empty database
        $this->artisan('cache:warm-entities', ['--type' => ['spell']])
            ->expectsOutput('Warming entity caches...')
            ->expectsOutput('✓ spells cached (0 entities)')
            ->expectsOutput('All entity caches warmed successfully!')
            ->expectsOutput('Total: 0 entities cached with 15-minute TTL')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_invalid_entity_type(): void
    {
        // Act & Assert: Run command with invalid type
        $this->artisan('cache:warm-entities', ['--type' => ['invalid']])
            ->assertExitCode(1);
    }
}
