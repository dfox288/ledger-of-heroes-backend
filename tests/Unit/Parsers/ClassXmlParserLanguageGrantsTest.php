<?php

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->group('unit-db')
    ->beforeEach(function () {
        $this->seed = true;
    });

beforeEach(function () {
    $this->parser = new ClassXmlParser;
});

it('detects thieves cant language grant from rogue', function () {
    $xml = '<compendium>
        <class>
            <name>Rogue</name>
            <hd>8</hd>
            <autolevel level="1">
                <feature>
                    <name>Thieves\' Cant</name>
                    <text>During your rogue training you learned thieves\' cant.</text>
                </feature>
            </autolevel>
        </class>
    </compendium>';

    $classes = $this->parser->parse($xml);
    $rogue = $classes[0];

    expect($rogue)->toHaveKey('languages')
        ->and($rogue['languages'])->toBeArray()
        ->and($rogue['languages'])->toHaveCount(1);

    $language = $rogue['languages'][0];
    expect($language['slug'])->toBe('core:thieves-cant')
        ->and($language['is_choice'])->toBeFalse();
});

it('detects druidic language grant from druid', function () {
    $xml = '<compendium>
        <class>
            <name>Druid</name>
            <hd>8</hd>
            <autolevel level="1">
                <feature>
                    <name>Druidic</name>
                    <text>You know Druidic, the secret language of druids.</text>
                </feature>
            </autolevel>
        </class>
    </compendium>';

    $classes = $this->parser->parse($xml);
    $druid = $classes[0];

    expect($druid)->toHaveKey('languages')
        ->and($druid['languages'])->toBeArray()
        ->and($druid['languages'])->toHaveCount(1);

    $language = $druid['languages'][0];
    expect($language['slug'])->toBe('core:druidic')
        ->and($language['is_choice'])->toBeFalse();
});

it('returns empty languages for classes without language grants', function () {
    $xml = '<compendium>
        <class>
            <name>Fighter</name>
            <hd>10</hd>
            <autolevel level="1">
                <feature>
                    <name>Fighting Style</name>
                    <text>You adopt a particular style of fighting as your specialty.</text>
                </feature>
            </autolevel>
        </class>
    </compendium>';

    $classes = $this->parser->parse($xml);
    $fighter = $classes[0];

    expect($fighter)->toHaveKey('languages')
        ->and($fighter['languages'])->toBeArray()
        ->and($fighter['languages'])->toBeEmpty();
});

it('ignores features that do not match any language', function () {
    $xml = '<compendium>
        <class>
            <name>Barbarian</name>
            <hd>12</hd>
            <autolevel level="1">
                <feature>
                    <name>Rage</name>
                    <text>In battle, you fight with primal ferocity.</text>
                </feature>
                <feature>
                    <name>Unarmored Defense</name>
                    <text>While you are not wearing any armor.</text>
                </feature>
            </autolevel>
        </class>
    </compendium>';

    $classes = $this->parser->parse($xml);
    $barbarian = $classes[0];

    expect($barbarian['languages'])->toBeEmpty();
});

it('parses real rogue xml for thieves cant', function () {
    $xmlPath = base_path('import-files/class-rogue-phb.xml');

    if (! file_exists($xmlPath)) {
        $this->markTestSkipped('Rogue XML file not found at: '.$xmlPath);
    }

    $xml = file_get_contents($xmlPath);
    $classes = $this->parser->parse($xml);
    $rogue = $classes[0];

    expect($rogue['name'])->toBe('Rogue')
        ->and($rogue)->toHaveKey('languages');

    $thievesCant = collect($rogue['languages'])->firstWhere('slug', 'core:thieves-cant');
    expect($thievesCant)->not->toBeNull("Thieves' Cant should be detected in Rogue")
        ->and($thievesCant['is_choice'])->toBeFalse();
});

it('parses real druid xml for druidic', function () {
    $xmlPath = base_path('import-files/class-druid-phb.xml');

    if (! file_exists($xmlPath)) {
        $this->markTestSkipped('Druid XML file not found at: '.$xmlPath);
    }

    $xml = file_get_contents($xmlPath);
    $classes = $this->parser->parse($xml);
    $druid = $classes[0];

    expect($druid['name'])->toBe('Druid')
        ->and($druid)->toHaveKey('languages');

    $druidic = collect($druid['languages'])->firstWhere('slug', 'core:druidic');
    expect($druidic)->not->toBeNull('Druidic should be detected in Druid')
        ->and($druidic['is_choice'])->toBeFalse();
});
