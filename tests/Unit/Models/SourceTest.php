<?php

namespace Tests\Unit\Models;

use App\Models\Source;
use Tests\TestCase;

class SourceTest extends TestCase
{
    public function test_source_model_exists(): void
    {
        $source = new Source();
        $this->assertInstanceOf(Source::class, $source);
    }

    public function test_source_does_not_use_timestamps(): void
    {
        $source = new Source();
        $this->assertFalse($source->timestamps);
    }

    public function test_source_has_fillable_attributes(): void
    {
        $source = new Source();
        $fillable = ['code', 'name', 'publisher', 'publication_year', 'edition'];
        $this->assertEquals($fillable, $source->getFillable());
    }
}
