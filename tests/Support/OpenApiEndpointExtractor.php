<?php

namespace Tests\Support;

class OpenApiEndpointExtractor
{
    private static ?array $cachedEndpoints = null;

    private static ?string $specPath = null;

    /**
     * Endpoints that require authentication - skip for health checks.
     */
    private const SKIP_PREFIXES = [
        '/v1/characters',
        '/v1/auth',
    ];

    /**
     * Set custom path to api.json (useful for testing).
     */
    public static function setSpecPath(?string $path): void
    {
        self::$specPath = $path;
        self::$cachedEndpoints = null;
    }

    /**
     * Get all testable GET endpoints from api.json.
     *
     * @return array<string, array{path: string, params: array<string>, paginated: bool}>
     */
    public static function getTestableEndpoints(): array
    {
        if (self::$cachedEndpoints !== null) {
            return self::$cachedEndpoints;
        }

        $specPath = self::$specPath ?? dirname(__DIR__, 2).'/api.json';
        if (! file_exists($specPath)) {
            throw new \RuntimeException('api.json not found at: '.$specPath);
        }

        $spec = json_decode(file_get_contents($specPath), true);
        $endpoints = [];

        foreach ($spec['paths'] ?? [] as $path => $methods) {
            // Only GET methods
            if (! isset($methods['get'])) {
                continue;
            }

            // Skip auth-required endpoints
            if (self::shouldSkipPath($path)) {
                continue;
            }

            // Extract path parameters
            preg_match_all('/\{(\w+)\}/', $path, $matches);
            $params = $matches[1] ?? [];

            // Skip multi-parameter routes
            if (count($params) > 1) {
                continue;
            }

            // Determine if paginated (has meta in 200 response schema)
            $paginated = self::isPaginated($methods['get']);

            $key = "GET {$path}";
            $endpoints[$key] = [
                'path' => $path,
                'params' => $params,
                'paginated' => $paginated,
            ];
        }

        // Sort by path for consistent ordering
        ksort($endpoints);

        self::$cachedEndpoints = $endpoints;

        return $endpoints;
    }

    /**
     * Get parameter-to-fixture mapping for path substitution.
     *
     * @return array<string, string> Maps param name to fixture key
     */
    public static function getParamFixtureMap(): array
    {
        return [
            'spell' => 'spell',
            'monster' => 'monster',
            'class' => 'class',
            'race' => 'race',
            'background' => 'background',
            'feat' => 'feat',
            'item' => 'item',
            'optionalFeature' => 'optionalFeature',
            'abilityScore' => 'abilityScore',
            'alignment' => 'alignment',
            'condition' => 'condition',
            'damageType' => 'damageType',
            'itemProperty' => 'itemProperty',
            'itemType' => 'itemType',
            'language' => 'language',
            'proficiencyType' => 'proficiencyType',
            'size' => 'size',
            'skill' => 'skill',
            'source' => 'source',
            'spellSchool' => 'spellSchool',
        ];
    }

    private static function shouldSkipPath(string $path): bool
    {
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function isPaginated(array $operation): bool
    {
        // Check operationId - Laravel convention: *.index = paginated, *.show = single
        $operationId = $operation['operationId'] ?? '';
        if (str_ends_with($operationId, '.index')) {
            return true;
        }

        $schema = $operation['responses']['200']['content']['application/json']['schema'] ?? [];

        // Check if schema has 'meta' property (Laravel pagination)
        if (isset($schema['properties']['meta'])) {
            return true;
        }

        // Check if it references a paginated response
        if (isset($schema['$ref']) && str_contains($schema['$ref'], 'Paginated')) {
            return true;
        }

        return false;
    }

    /**
     * Clear the cached endpoints (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cachedEndpoints = null;
        self::$specPath = null;
    }
}
