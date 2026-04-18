<?php

namespace Tests\Feature\Console;

use App\Models\Monster;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ImportMonstersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required lookup data
        Size::firstOrCreate(['code' => 'L'], ['name' => 'Large']);
        Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        Size::firstOrCreate(['code' => 'S'], ['name' => 'Small']);
    }

    #[Test]
    public function it_imports_monsters_from_xml_file_via_command(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutput('Importing monsters from: '.$xmlPath)
            ->assertExitCode(0);

        $this->assertEquals(3, Monster::count());
    }

    #[Test]
    public function it_displays_strategy_statistics(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutputToContain('Strategy Statistics:')
            ->expectsOutputToContain('DragonStrategy')
            ->expectsOutputToContain('SpellcasterStrategy')
            ->expectsOutputToContain('DefaultStrategy')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_returns_error_for_missing_file(): void
    {
        $this->artisan('import:monsters', ['file' => 'non-existent.xml'])
            ->expectsOutput('File not found: non-existent.xml')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_displays_success_message_with_count(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutputToContain('Successfully imported 3 monsters')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_log_file_location(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $logPath = 'storage/logs/import-strategy-'.date('Y-m-d').'.log';

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutputToContain($logPath)
            ->assertExitCode(0);
    }
}
