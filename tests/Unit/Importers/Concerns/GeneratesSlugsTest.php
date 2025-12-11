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
        $slug = $this->generateSlug('Hill Dwarf', []);
        $this->assertEquals('core:hill-dwarf', $slug);
    }

    #[Test]
    public function it_generates_hierarchical_slug_with_parent()
    {
        $sources = [['code' => 'PHB', 'pages' => '123']];
        $slug = $this->generateSlug('Battle Master', $sources, 'phb:fighter');
        $this->assertEquals('phb:fighter-battle-master', $slug);
    }

    #[Test]
    public function it_handles_special_characters()
    {
        $slug = $this->generateSlug("Smith's Tools", []);
        $this->assertEquals('core:smiths-tools', $slug);
    }

    #[Test]
    public function it_handles_parentheses_in_names()
    {
        $slug = $this->generateSlug('Dwarf (Hill)', []);
        $this->assertEquals('core:dwarf-hill', $slug);
    }

    #[Test]
    public function it_handles_multiple_spaces()
    {
        $slug = $this->generateSlug('Very   Long    Name', []);
        $this->assertEquals('core:very-long-name', $slug);
    }

    #[Test]
    public function it_generates_slug_with_source_prefix()
    {
        $sources = [['code' => 'PHB', 'pages' => '123']];
        $slug = $this->generateSlug('high-elf', $sources);

        $this->assertEquals('phb:high-elf', $slug);
    }

    #[Test]
    public function it_uses_first_source_for_slug()
    {
        $sources = [
            ['code' => 'PHB', 'pages' => '123'],
            ['code' => 'XGE', 'pages' => '45'],
        ];
        $slug = $this->generateSlug('magic-missile', $sources);

        $this->assertEquals('phb:magic-missile', $slug);
    }

    #[Test]
    public function it_lowercases_source_code_in_slug()
    {
        $sources = [['code' => 'XGE', 'pages' => '']];
        $slug = $this->generateSlug('shadow-blade', $sources);

        $this->assertEquals('xge:shadow-blade', $slug);
    }

    #[Test]
    public function it_defaults_to_core_prefix_for_empty_sources()
    {
        $slug = $this->generateSlug('orphan-entity', []);

        $this->assertEquals('core:orphan-entity', $slug);
    }

    #[Test]
    public function it_defaults_to_core_prefix_for_sources_without_code()
    {
        $sources = [['pages' => '123']]; // Missing 'code' key
        $slug = $this->generateSlug('invalid-entity', $sources);

        $this->assertEquals('core:invalid-entity', $slug);
    }

    #[Test]
    public function it_generates_core_slug_for_universal_entities()
    {
        $slug = $this->generateSlug('common', []);

        $this->assertEquals('core:common', $slug);
    }

    #[Test]
    public function it_generates_core_slug_for_skill()
    {
        $slug = $this->generateSlug('athletics', []);

        $this->assertEquals('core:athletics', $slug);
    }
}
