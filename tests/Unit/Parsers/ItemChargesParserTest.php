<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesCharges;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ItemChargesParserTest extends TestCase
{
    use ParsesCharges;

    #[Test]
    public function it_parses_fixed_charge_count(): void
    {
        $text = 'This wand has 3 charges. While holding it, you can use an action to expend 1 of its charges.';

        $result = $this->parseCharges($text);

        $this->assertEquals(3, $result['charges_max']);
    }

    #[Test]
    public function it_parses_starts_with_charge_count(): void
    {
        $text = 'This cube starts with 36 charges, and it regains 1d20 expended charges daily at dawn.';

        $result = $this->parseCharges($text);

        $this->assertEquals(36, $result['charges_max']);
    }

    #[Test]
    public function it_parses_dice_based_recharge_formulas(): void
    {
        $texts = [
            'regains 1d6+1 expended charges daily at dawn' => '1d6+1',
            'regains 1d3 expended charges daily at dawn' => '1d3',
            'regains 1d20 expended charges daily at dawn' => '1d20',
            'regains 1d6+4 expended charges daily at dawn' => '1d6+4',
        ];

        foreach ($texts as $text => $expected) {
            $result = $this->parseCharges($text);
            $this->assertEquals($expected, $result['recharge_formula'], "Failed for: $text");
        }
    }

    #[Test]
    public function it_parses_all_charges_recharge(): void
    {
        $text = 'The wand regains all expended charges daily at dawn.';

        $result = $this->parseCharges($text);

        $this->assertEquals('all', $result['recharge_formula']);
    }

    #[Test]
    public function it_parses_recharge_timing_dawn_and_dusk(): void
    {
        $dawnText = 'This staff has 10 charges. It regains 1d6+4 expended charges daily at dawn.';
        $duskText = 'This item regains charges daily at dusk.';

        $dawnResult = $this->parseCharges($dawnText);
        $duskResult = $this->parseCharges($duskText);

        $this->assertEquals('dawn', $dawnResult['recharge_timing']);
        $this->assertEquals('dusk', $duskResult['recharge_timing']);
    }

    #[Test]
    public function it_parses_rest_based_recharge_timing(): void
    {
        $shortRestText = 'This ring regains 1d3 charges after a short rest.';
        $longRestText = 'This helm regains all expended charges after a long rest.';

        $shortResult = $this->parseCharges($shortRestText);
        $longResult = $this->parseCharges($longRestText);

        $this->assertEquals('short rest', $shortResult['recharge_timing']);
        $this->assertEquals('long rest', $longResult['recharge_timing']);
    }

    #[Test]
    public function it_handles_items_without_charges(): void
    {
        $text = 'This is a normal longsword. It deals 1d8 slashing damage.';

        $result = $this->parseCharges($text);

        $this->assertNull($result['charges_max']);
        $this->assertNull($result['recharge_formula']);
        $this->assertNull($result['recharge_timing']);
    }

    #[Test]
    public function it_parses_complete_wand_of_smiles_description(): void
    {
        $text = 'This wand has 3 charges. While holding it, you can use an action to expend 1 of its charges and target a humanoid you can see within 30 feet of you. The target must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute. The wand regains all expended charges daily at dawn. If you expend the wand\'s last charge, roll a d20. On a 1, the wand transforms into a wand of scowls.';

        $result = $this->parseCharges($text);

        $this->assertEquals(3, $result['charges_max']);
        $this->assertEquals('all', $result['recharge_formula']);
        $this->assertEquals('dawn', $result['recharge_timing']);
    }

    #[Test]
    public function it_parses_complete_wand_of_binding_description(): void
    {
        $text = 'This wand has 7 charges for the following properties. It regains 1d6 + 1 expended charges daily at dawn. If you expend the wand\'s last charge, roll a d20. On a 1, the wand crumbles into ashes and is destroyed.';

        $result = $this->parseCharges($text);

        $this->assertEquals(7, $result['charges_max']);
        $this->assertEquals('1d6+1', $result['recharge_formula']);
        $this->assertEquals('dawn', $result['recharge_timing']);
    }

    #[Test]
    public function it_parses_cubic_gate_with_large_capacity(): void
    {
        $text = 'This cube is about an inch across. Each face has a distinct marking on it that can be pressed. The cube starts with 36 charges, and it regains 1d20 expended charges daily at dawn.';

        $result = $this->parseCharges($text);

        $this->assertEquals(36, $result['charges_max']);
        $this->assertEquals('1d20', $result['recharge_formula']);
        $this->assertEquals('dawn', $result['recharge_timing']);
    }
}
