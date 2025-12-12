<?php

namespace Tests\Unit\Models;

use App\Models\EntityLanguage;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntityLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_reference_relationship_to_parent_entity(): void
    {
        $race = Race::factory()->create();
        $entityLanguage = EntityLanguage::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
        ]);

        $this->assertInstanceOf(Race::class, $entityLanguage->reference);
        $this->assertEquals($race->id, $entityLanguage->reference->id);
    }
}
