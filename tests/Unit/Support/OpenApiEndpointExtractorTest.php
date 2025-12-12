<?php

use Tests\Support\OpenApiEndpointExtractor;

beforeEach(function () {
    OpenApiEndpointExtractor::clearCache();
});

it('extracts GET endpoints from api.json', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    expect($endpoints)->toBeArray();
    expect($endpoints)->not->toBeEmpty();

    // Should have the spells index endpoint
    expect($endpoints)->toHaveKey('GET /v1/spells');
    expect($endpoints['GET /v1/spells'])->toMatchArray([
        'path' => '/v1/spells',
        'params' => [],
        'paginated' => true,
    ]);
});

it('extracts single-parameter endpoints', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    expect($endpoints)->toHaveKey('GET /v1/spells/{spell}');
    expect($endpoints['GET /v1/spells/{spell}']['params'])->toBe(['spell']);
});

it('excludes multi-parameter endpoints', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    // Should not include nested routes with multiple params
    $multiParamEndpoints = array_filter($endpoints, fn ($e) => count($e['params']) > 1);
    expect($multiParamEndpoints)->toBeEmpty();
});

it('excludes auth-required character endpoints', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    // Character endpoints require auth - skip them for health check
    expect($endpoints)->not->toHaveKey('GET /v1/characters');
    expect($endpoints)->not->toHaveKey('GET /v1/characters/{character}');
});

it('excludes search endpoint (requires query param)', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    expect($endpoints)->not->toHaveKey('GET /v1/search');
});

it('excludes nested lookup endpoints', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    // Nested lookups like /lookups/proficiency-types/{param}/backgrounds are skipped
    $nestedLookups = array_filter(
        $endpoints,
        fn ($e, $k) => str_contains($k, '/lookups/') && ! empty($e['params']),
        ARRAY_FILTER_USE_BOTH
    );
    expect($nestedLookups)->toBeEmpty();
});

it('marks lookup index endpoints as non-paginated', function () {
    $endpoints = OpenApiEndpointExtractor::getTestableEndpoints();

    // Lookup index endpoints return simple arrays, not paginated
    expect($endpoints)->toHaveKey('GET /v1/lookups/alignments');
    expect($endpoints['GET /v1/lookups/alignments']['paginated'])->toBeFalse();
});
