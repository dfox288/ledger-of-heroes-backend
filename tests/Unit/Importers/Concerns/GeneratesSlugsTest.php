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
}
