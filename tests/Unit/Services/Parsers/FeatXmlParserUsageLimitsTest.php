<?php

use App\Enums\ResetTiming;
use App\Services\Parsers\FeatXmlParser;

describe('FeatXmlParser usage limits parsing', function () {
    beforeEach(function () {
        $this->parser = new FeatXmlParser;
    });

    it('parses luck points from Lucky feat', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Lucky</name>
                <text>You have inexplicable luck that seems to kick in at just the right moment.
        You have 3 luck points. Whenever you make an attack roll, an ability check, or a saving throw, you can spend one luck point to roll an additional d20.
        You regain your expended luck points when you finish a long rest.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats)->toHaveCount(1);
        expect($feats[0]['base_uses'])->toBe(3);
        expect($feats[0]['resets_on'])->toBe(ResetTiming::LONG_REST);
    });

    it('parses single use from Magic Initiate pattern', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Magic Initiate (Bard)</name>
                <text>You learn two bard cantrips of your choice.
        In addition, choose one 1st-level bard spell. You learn that spell and can cast it at its lowest level. Once you cast it, you must finish a long rest before you can cast it again using this feat.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['base_uses'])->toBe(1);
        expect($feats[0]['resets_on'])->toBe(ResetTiming::LONG_REST);
    });

    it('parses superiority die from Martial Adept feat', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Martial Adept</name>
                <text>You have martial training that allows you to perform special combat maneuvers.
        You gain one superiority die, which is a d6. This die is used to fuel your maneuvers.
        You regain your expended superiority dice when you finish a short or long rest.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['base_uses'])->toBe(1);
        expect($feats[0]['resets_on'])->toBe(ResetTiming::SHORT_REST);
    });

    it('parses numeric uses from "N times" pattern', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Test Feat</name>
                <text>You can use this feature 2 times before you must finish a long rest.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['base_uses'])->toBe(2);
    });

    it('parses word number uses', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Test Feat</name>
                <text>You can use this ability twice before requiring a long rest.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['base_uses'])->toBe(2);
    });

    it('returns null for feats without usage limits', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Alert</name>
                <text>Always on the lookout for danger, you gain the following benefits:
        You gain a +5 bonus to initiative.
        You can't be surprised while you are conscious.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['base_uses'])->toBeNull();
        expect($feats[0]['resets_on'])->toBeNull();
    });

    it('parses proficiency bonus uses formula', function () {
        $xml = <<<'XML'
        <compendium>
            <feat>
                <name>Test Feat</name>
                <text>You can use this feature a number of times equal to your proficiency bonus. You regain all uses when you finish a long rest.</text>
            </feat>
        </compendium>
        XML;

        $feats = $this->parser->parse($xml);

        expect($feats[0]['uses_formula'])->toBe('proficiency');
        expect($feats[0]['resets_on'])->toBe(ResetTiming::LONG_REST);
    });
});
