<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesProjectileScaling;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class SpellProjectileScalingParserTest extends TestCase
{
    use ParsesProjectileScaling;

    #[Test]
    public function it_parses_magic_missile_dart_scaling(): void
    {
        $higherLevels = 'When you cast this spell using a spell slot of 2nd level or higher, the spell creates one more dart for each slot level above 1st.';
        $spellLevel = 1;

        $result = $this->parseProjectileScaling($higherLevels, $spellLevel);

        $this->assertNotNull($result);
        $this->assertEquals(3, $result['projectile_count']); // Magic Missile creates 3 darts at base
        $this->assertEquals(1, $result['projectile_per_level']);
        $this->assertEquals('dart', $result['projectile_name']);
    }

    #[Test]
    public function it_parses_scorching_ray_scaling(): void
    {
        $higherLevels = 'When you cast this spell using a spell slot of 3rd level or higher, you create one additional ray for each slot level above 2nd.';
        $spellLevel = 2;

        $result = $this->parseProjectileScaling($higherLevels, $spellLevel);

        $this->assertNotNull($result);
        $this->assertEquals(3, $result['projectile_count']); // Scorching Ray creates 3 rays at base
        $this->assertEquals(1, $result['projectile_per_level']);
        $this->assertEquals('ray', $result['projectile_name']);
    }

    #[Test]
    public function it_returns_null_for_non_projectile_scaling(): void
    {
        // Fireball scales damage, not projectiles
        $higherLevels = 'When you cast this spell using a spell slot of 4th level or higher, the damage increases by 1d6 for each slot level above 3rd.';
        $spellLevel = 3;

        $result = $this->parseProjectileScaling($higherLevels, $spellLevel);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_null_higher_levels(): void
    {
        $result = $this->parseProjectileScaling(null, 1);

        $this->assertNull($result);
    }

    #[Test]
    public function it_parses_target_scaling(): void
    {
        // Hold Person style - "target one additional creature"
        $higherLevels = 'When you cast this spell using a spell slot of 3rd level or higher, you can target one additional creature for each slot level above 2nd.';
        $spellLevel = 2;

        $result = $this->parseProjectileScaling($higherLevels, $spellLevel);

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['projectile_count']); // 1 target at base
        $this->assertEquals(1, $result['projectile_per_level']);
        $this->assertEquals('target', $result['projectile_name']);
    }

    #[Test]
    public function it_handles_two_more_pattern(): void
    {
        // Some spells use "two more" instead of "one more"
        $higherLevels = 'When you cast this spell using a spell slot of 3rd level or higher, the spell creates two more bolts for each slot level above 2nd.';
        $spellLevel = 2;

        $result = $this->parseProjectileScaling($higherLevels, $spellLevel);

        $this->assertNotNull($result);
        $this->assertEquals(2, $result['projectile_per_level']);
        $this->assertEquals('bolt', $result['projectile_name']);
    }
}
