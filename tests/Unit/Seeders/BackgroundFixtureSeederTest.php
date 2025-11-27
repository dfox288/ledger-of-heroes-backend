<?php

namespace Tests\Unit\Seeders;

use App\Models\Background;
use Database\Seeders\Testing\BackgroundFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackgroundFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookups

    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal test fixture matching the actual format from ExtractFixturesCommand::formatBackground()
        $fixturePath = base_path('tests/fixtures/entities/backgrounds.json');
        File::ensureDirectoryExists(dirname($fixturePath));
        File::put($fixturePath, json_encode([
            [
                'name' => 'Test Acolyte',
                'slug' => 'test-acolyte',
                'skill_proficiencies' => [
                    [
                        'skill_slug' => 'insight',
                        'proficiency_name' => 'Insight',
                        'is_choice' => false,
                        'quantity' => null,
                    ],
                    [
                        'skill_slug' => 'religion',
                        'proficiency_name' => 'Religion',
                        'is_choice' => false,
                        'quantity' => null,
                    ],
                ],
                'tool_proficiencies' => [],
                'language_proficiencies' => [
                    [
                        'proficiency_name' => 'Any two languages',
                        'is_choice' => true,
                        'quantity' => 2,
                    ],
                ],
                'other_proficiencies' => [],
                'features' => [
                    [
                        'name' => 'Shelter of the Faithful',
                        'category' => 'background_feature',
                        'description' => 'You command the respect of those who share your faith.',
                    ],
                ],
                'equipment' => [],
                'source' => 'PHB',
                'pages' => '127',
            ],
            [
                'name' => 'Test Criminal',
                'slug' => 'test-criminal',
                'skill_proficiencies' => [
                    [
                        'skill_slug' => 'deception',
                        'proficiency_name' => 'Deception',
                        'is_choice' => false,
                        'quantity' => null,
                    ],
                    [
                        'skill_slug' => 'stealth',
                        'proficiency_name' => 'Stealth',
                        'is_choice' => false,
                        'quantity' => null,
                    ],
                ],
                'tool_proficiencies' => [
                    [
                        'proficiency_type' => 'tool',
                        'proficiency_subcategory' => 'thieves_tools',
                        'proficiency_name' => "Thieves' Tools",
                        'item_slug' => 'thieves-tools',
                        'is_choice' => false,
                        'quantity' => null,
                    ],
                    [
                        'proficiency_type' => 'tool',
                        'proficiency_subcategory' => 'gaming_set',
                        'proficiency_name' => 'Gaming Set',
                        'item_slug' => null,
                        'is_choice' => true,
                        'quantity' => 1,
                    ],
                ],
                'language_proficiencies' => [],
                'other_proficiencies' => [],
                'features' => [
                    [
                        'name' => 'Criminal Contact',
                        'category' => 'background_feature',
                        'description' => 'You have a reliable and trustworthy contact.',
                    ],
                ],
                'equipment' => [],
                'source' => 'PHB',
                'pages' => '129',
            ],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test fixture
        $fixturePath = base_path('tests/fixtures/entities/backgrounds.json');
        if (File::exists($fixturePath)) {
            File::delete($fixturePath);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_backgrounds_from_fixture(): void
    {
        $this->assertDatabaseMissing('backgrounds', ['slug' => 'test-acolyte']);

        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $this->assertDatabaseHas('backgrounds', [
            'slug' => 'test-acolyte',
            'name' => 'Test Acolyte',
        ]);

        $this->assertDatabaseHas('backgrounds', [
            'slug' => 'test-criminal',
            'name' => 'Test Criminal',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_skill_proficiencies(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-acolyte')->first();
        $this->assertNotNull($background);

        // Check that skill proficiencies were created
        $skillProfs = $background->proficiencies
            ->where('proficiency_type', 'skill');

        $this->assertCount(2, $skillProfs);

        // Check first skill proficiency (Insight)
        $insightProf = $skillProfs->firstWhere('proficiency_name', 'Insight');
        $this->assertNotNull($insightProf);
        $this->assertEquals('insight', $insightProf->skill->slug);
        $this->assertFalse($insightProf->is_choice);

        // Check second skill proficiency (Religion)
        $religionProf = $skillProfs->firstWhere('proficiency_name', 'Religion');
        $this->assertNotNull($religionProf);
        $this->assertEquals('religion', $religionProf->skill->slug);
        $this->assertFalse($religionProf->is_choice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_tool_proficiencies(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-criminal')->first();
        $this->assertNotNull($background);

        // Check that tool proficiencies were created
        $toolProfs = $background->proficiencies
            ->where('proficiency_type', 'tool');

        $this->assertCount(2, $toolProfs);

        // Check first tool proficiency (Thieves' Tools - specific item reference)
        $thievesTools = $toolProfs->firstWhere('proficiency_name', "Thieves' Tools");
        $this->assertNotNull($thievesTools);
        $this->assertEquals('thieves_tools', $thievesTools->proficiency_subcategory);
        // Note: Item won't exist in minimal test DB, just verify it tried to link by slug
        $this->assertFalse($thievesTools->is_choice);

        // Check second tool proficiency (Gaming Set - choice, no specific item)
        $gamingSet = $toolProfs->firstWhere('proficiency_name', 'Gaming Set');
        $this->assertNotNull($gamingSet);
        $this->assertEquals('gaming_set', $gamingSet->proficiency_subcategory);
        $this->assertNull($gamingSet->item_id);
        $this->assertTrue($gamingSet->is_choice);
        $this->assertEquals(1, $gamingSet->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_language_proficiencies(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-acolyte')->first();
        $this->assertNotNull($background);

        // Check that language proficiencies were created
        $languageProfs = $background->proficiencies
            ->where('proficiency_type', 'language');

        $this->assertCount(1, $languageProfs);

        $langProf = $languageProfs->first();
        $this->assertEquals('Any two languages', $langProf->proficiency_name);
        $this->assertTrue($langProf->is_choice);
        $this->assertEquals(2, $langProf->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_background_features(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-acolyte')->first();
        $this->assertNotNull($background);

        // Check that feature (trait) was created
        $this->assertCount(1, $background->traits);
        $trait = $background->traits->first();
        $this->assertEquals('Shelter of the Faithful', $trait->name);
        $this->assertEquals('background_feature', $trait->category);
        $this->assertStringContainsString('respect of those who share your faith', $trait->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entity_sources(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-acolyte')->first();
        $this->assertNotNull($background);

        // Check that entity source was created
        $this->assertCount(1, $background->sources);
        $this->assertEquals('PHB', $background->sources->first()->source->code);
        $this->assertEquals('127', $background->sources->first()->pages);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_backgrounds_without_tool_proficiencies(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-acolyte')->first();
        $this->assertNotNull($background);

        // Acolyte has no tool proficiencies in our fixture
        $toolProfs = $background->proficiencies
            ->where('proficiency_type', 'tool');

        $this->assertCount(0, $toolProfs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_backgrounds_without_language_proficiencies(): void
    {
        $seeder = new BackgroundFixtureSeeder;
        $seeder->run();

        $background = Background::where('slug', 'test-criminal')->first();
        $this->assertNotNull($background);

        // Criminal has no language proficiencies in our fixture
        $languageProfs = $background->proficiencies
            ->where('proficiency_type', 'language');

        $this->assertCount(0, $languageProfs);
    }
}
