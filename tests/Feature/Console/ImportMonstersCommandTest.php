<?php

namespace Tests\Feature\Console;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMonstersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required lookup data
        \App\Models\Size::firstOrCreate(['code' => 'L'], ['name' => 'Large']);
        \App\Models\Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        \App\Models\Size::firstOrCreate(['code' => 'S'], ['name' => 'Small']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_imports_monsters_from_xml_file_via_command(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutput('Importing monsters from: '.$xmlPath)
            ->assertExitCode(0);

        $this->assertEquals(3, Monster::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_error_for_missing_file(): void
    {
        $this->artisan('import:monsters', ['file' => 'non-existent.xml'])
            ->expectsOutput('File not found: non-existent.xml')
            ->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_success_message_with_count(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutputToContain('Successfully imported 3 monsters')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_log_file_location(): void
    {
        $xmlPath = base_path('tests/Fixtures/xml/monsters/test-monsters.xml');

        $logPath = 'storage/logs/import-strategy-'.date('Y-m-d').'.log';

        $this->artisan('import:monsters', ['file' => $xmlPath])
            ->expectsOutputToContain($logPath)
            ->assertExitCode(0);
    }
}
