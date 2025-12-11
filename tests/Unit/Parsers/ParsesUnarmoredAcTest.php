<?php

use App\Services\Parsers\Concerns\ParsesUnarmoredAc;

/**
 * Test harness class that uses the ParsesUnarmoredAc trait.
 */
class UnarmoredAcParserTestHarness
{
    use ParsesUnarmoredAc;

    public function parse(string $text): ?array
    {
        return $this->parseUnarmoredAc($text);
    }
}

describe('ParsesUnarmoredAc', function () {
    beforeEach(function () {
        $this->parser = new UnarmoredAcParserTestHarness;
    });

    describe('Dragon Hide feat pattern', function () {
        it('parses "calculate your AC as 13 + your Dexterity modifier"', function () {
            $text = 'While you aren\'t wearing armor, you can calculate your AC as 13 + your Dexterity modifier. You can use a shield and still gain this benefit.';

            $result = $this->parser->parse($text);

            expect($result)->not->toBeNull();
            expect($result['base_ac'])->toBe(13);
            expect($result['ability_code'])->toBe('DEX');
            expect($result['allows_shield'])->toBeTrue();
            expect($result['replaces_armor'])->toBeFalse();
        });
    });

    describe('Lizardfolk race pattern', function () {
        it('parses "your AC is 13 + your Dexterity modifier"', function () {
            $text = 'You have tough, scaly skin. When you aren\'t wearing armor, your AC is 13 + your Dexterity modifier. You can use your natural armor to determine your AC if the armor you wear would leave you with a lower AC. A shield\'s benefits apply as normal while you use your natural armor.';

            $result = $this->parser->parse($text);

            expect($result)->not->toBeNull();
            expect($result['base_ac'])->toBe(13);
            expect($result['ability_code'])->toBe('DEX');
            expect($result['allows_shield'])->toBeTrue();
            expect($result['replaces_armor'])->toBeFalse();
        });
    });

    describe('Loxodon race pattern', function () {
        it('parses "your AC is 12 + your Constitution modifier"', function () {
            $text = 'You have thick, leathery skin. When you aren\'t wearing armor, your AC is 12 + your Constitution modifier. You can use your natural armor to determine your AC if the armor you wear would leave you with a lower AC. A shield\'s benefits apply as normal while you use your natural armor.';

            $result = $this->parser->parse($text);

            expect($result)->not->toBeNull();
            expect($result['base_ac'])->toBe(12);
            expect($result['ability_code'])->toBe('CON');
            expect($result['allows_shield'])->toBeTrue();
            expect($result['replaces_armor'])->toBeFalse();
        });
    });

    describe('Tortle race pattern', function () {
        it('parses "base AC of 17" with no ability modifier', function () {
            $text = 'Your shell provides you a base AC of 17 (your Dexterity modifier doesn\'t affect this number). You can\'t wear light, medium, or heavy armor, but if you are using a shield, you can apply the shield\'s bonus as normal.';

            $result = $this->parser->parse($text);

            expect($result)->not->toBeNull();
            expect($result['base_ac'])->toBe(17);
            expect($result['ability_code'])->toBeNull();
            expect($result['allows_shield'])->toBeTrue();
            expect($result['replaces_armor'])->toBeTrue();
        });
    });

    describe('Locathah race pattern', function () {
        it('parses natural armor with DEX modifier', function () {
            $text = 'Your scales provide a base AC of 12 + your Dexterity modifier.';

            $result = $this->parser->parse($text);

            expect($result)->not->toBeNull();
            expect($result['base_ac'])->toBe(12);
            expect($result['ability_code'])->toBe('DEX');
        });
    });

    describe('shield detection', function () {
        it('detects "You can use a shield"', function () {
            $text = 'Your AC is 13 + your Dexterity modifier. You can use a shield and still gain this benefit.';

            $result = $this->parser->parse($text);

            expect($result['allows_shield'])->toBeTrue();
        });

        it('detects "using a shield"', function () {
            $text = 'Your base AC is 17. If you are using a shield, you can apply the shield\'s bonus.';

            $result = $this->parser->parse($text);

            expect($result['allows_shield'])->toBeTrue();
        });

        it('detects "shield\'s benefits apply"', function () {
            $text = 'Your AC is 13 + your Dexterity modifier. A shield\'s benefits apply as normal.';

            $result = $this->parser->parse($text);

            expect($result['allows_shield'])->toBeTrue();
        });

        it('defaults to true when no shield mention', function () {
            $text = 'Your AC is 13 + your Dexterity modifier when not wearing armor.';

            $result = $this->parser->parse($text);

            // Default to allowing shields unless explicitly prohibited
            expect($result['allows_shield'])->toBeTrue();
        });
    });

    describe('armor replacement detection', function () {
        it('detects "can\'t wear light, medium, or heavy armor"', function () {
            $text = 'Your base AC is 17. You can\'t wear light, medium, or heavy armor.';

            $result = $this->parser->parse($text);

            expect($result['replaces_armor'])->toBeTrue();
        });

        it('detects "cannot wear armor"', function () {
            $text = 'Your base AC is 15. You cannot wear armor of any kind.';

            $result = $this->parser->parse($text);

            expect($result['replaces_armor'])->toBeTrue();
        });

        it('defaults to false when can choose better', function () {
            $text = 'Your AC is 13 + your Dexterity modifier. You can use your natural armor to determine your AC if the armor you wear would leave you with a lower AC.';

            $result = $this->parser->parse($text);

            expect($result['replaces_armor'])->toBeFalse();
        });
    });

    describe('no match scenarios', function () {
        it('returns null when no AC pattern found', function () {
            $text = 'You gain proficiency in light armor.';

            $result = $this->parser->parse($text);

            expect($result)->toBeNull();
        });

        it('returns null for regular armor descriptions', function () {
            $text = 'This armor has an AC of 14 + Dexterity modifier (max 2).';

            $result = $this->parser->parse($text);

            // This is armor item text, not natural armor - should not match
            expect($result)->toBeNull();
        });

        it('returns null for empty text', function () {
            $result = $this->parser->parse('');

            expect($result)->toBeNull();
        });

        it('returns null for AC values below valid range', function () {
            $text = 'Your AC is 5 + your Dexterity modifier.';

            $result = $this->parser->parse($text);

            // AC below 10 is invalid for natural armor
            expect($result)->toBeNull();
        });

        it('returns null for AC values above valid range', function () {
            $text = 'Your AC is 25 + your Dexterity modifier.';

            $result = $this->parser->parse($text);

            // AC above 20 is invalid for natural armor
            expect($result)->toBeNull();
        });
    });
});
