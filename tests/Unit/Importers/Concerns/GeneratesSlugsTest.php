<?php

namespace Tests\Unit\Importers\Concerns;

use App\Services\Importers\Concerns\GeneratesSlugs;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class GeneratesSlugsTest extends TestCase
{
    use GeneratesSlugs;

    #[Test]
    public function it_generates_simple_slug_from_name()
    {
        $slug = $this->generateSlug('Hill Dwarf');
        $this->assertEquals('hill-dwarf', $slug);
    }

    #[Test]
    public function it_generates_hierarchical_slug_with_parent()
    {
        $slug = $this->generateSlug('Battle Master', 'fighter');
        $this->assertEquals('fighter-battle-master', $slug);
    }

    #[Test]
    public function it_handles_special_characters()
    {
        $slug = $this->generateSlug("Smith's Tools");
        $this->assertEquals('smiths-tools', $slug);
    }

    #[Test]
    public function it_handles_parentheses_in_names()
    {
        $slug = $this->generateSlug('Dwarf (Hill)');
        $this->assertEquals('dwarf-hill', $slug);
    }

    #[Test]
    public function it_handles_multiple_spaces()
    {
        $slug = $this->generateSlug('Very   Long    Name');
        $this->assertEquals('very-long-name', $slug);
    }

    #[Test]
    public function it_generates_full_slug_with_source_prefix()
    {
        $sources = [['code' => 'PHB', 'pages' => '123']];
        $fullSlug = $this->generateFullSlug('high-elf', $sources);

        $this->assertEquals('phb:high-elf', $fullSlug);
    }

    #[Test]
    public function it_uses_first_source_for_full_slug()
    {
        $sources = [
            ['code' => 'PHB', 'pages' => '123'],
            ['code' => 'XGE', 'pages' => '45'],
        ];
        $fullSlug = $this->generateFullSlug('magic-missile', $sources);

        $this->assertEquals('phb:magic-missile', $fullSlug);
    }

    #[Test]
    public function it_lowercases_source_code_in_full_slug()
    {
        $sources = [['code' => 'XGE', 'pages' => '']];
        $fullSlug = $this->generateFullSlug('shadow-blade', $sources);

        $this->assertEquals('xge:shadow-blade', $fullSlug);
    }

    #[Test]
    public function it_returns_null_for_empty_sources()
    {
        $fullSlug = $this->generateFullSlug('orphan-entity', []);

        $this->assertNull($fullSlug);
    }

    #[Test]
    public function it_returns_null_for_sources_without_code()
    {
        $sources = [['pages' => '123']]; // Missing 'code' key
        $fullSlug = $this->generateFullSlug('invalid-entity', $sources);

        $this->assertNull($fullSlug);
    }

    #[Test]
    public function it_generates_core_full_slug_for_universal_entities()
    {
        $fullSlug = $this->generateCoreFullSlug('common');

        $this->assertEquals('core:common', $fullSlug);
    }

    #[Test]
    public function it_generates_core_full_slug_for_skill()
    {
        $fullSlug = $this->generateCoreFullSlug('athletics');

        $this->assertEquals('core:athletics', $fullSlug);
    }
}
