<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use Illuminate\Http\Request;

/**
 * Captures the full state of a character at a point in time.
 * Uses internal Laravel routing to avoid network issues.
 */
class StateSnapshot
{
    /**
     * Capture full character state from all relevant endpoints.
     */
    public function capture(int|string $characterId): array
    {
        $snapshot = [
            'timestamp' => now()->toIso8601String(),
            'character_id' => $characterId,
        ];

        // Fetch all endpoints
        $snapshot['character'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");
        $snapshot['stats'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/stats");
        $snapshot['pending_choices'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");
        $snapshot['spells'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/spells");
        $snapshot['equipment'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/equipment");
        $snapshot['languages'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/languages");
        $snapshot['proficiencies'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/proficiencies");
        $snapshot['features'] = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/features");

        // Add derived/flattened fields for easier comparison
        $snapshot['derived'] = $this->deriveComparisonFields($snapshot);

        return $snapshot;
    }

    /**
     * Make an internal request to the Laravel application.
     */
    private function makeRequest(string $method, string $uri, array $data = []): array
    {
        $request = Request::create(
            $uri,
            $method,
            $method === 'GET' ? $data : [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $method !== 'GET' ? json_encode($data) : null
        );

        $response = app()->handle($request);
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();

        $decoded = json_decode($content, true) ?? [];

        if ($statusCode >= 400) {
            return [
                'error' => true,
                'status' => $statusCode,
                'message' => $decoded['message'] ?? 'Unknown error',
            ];
        }

        return $decoded;
    }

    /**
     * Derive flattened fields for easier before/after comparison.
     */
    private function deriveComparisonFields(array $snapshot): array
    {
        $character = $snapshot['character']['data'] ?? [];
        $stats = $snapshot['stats']['data'] ?? [];

        return [
            // Core identity
            'race_slug' => $character['race']['slug'] ?? null,
            'race_name' => $character['race']['name'] ?? null,
            'background_slug' => $character['background']['slug'] ?? null,
            'background_name' => $character['background']['name'] ?? null,
            'class_slugs' => collect($character['classes'] ?? [])
                ->pluck('class.slug')
                ->toArray(),

            // Ability scores (base)
            'ability_scores' => $character['ability_scores'] ?? [],
            'modifiers' => $character['modifiers'] ?? [],

            // Physical traits
            'speed' => $character['speed'] ?? null,
            'speeds' => $character['speeds'] ?? [],
            'size' => $character['size'] ?? null,

            // Spells by source
            'spells_by_source' => $this->groupBySource($snapshot['spells']['data'] ?? []),
            'spell_count' => count($snapshot['spells']['data'] ?? []),

            // Languages by source
            'languages' => collect($snapshot['languages']['data'] ?? [])
                ->pluck('slug')
                ->toArray(),
            'language_count' => count($snapshot['languages']['data'] ?? []),

            // Proficiencies by source
            'proficiencies' => collect($snapshot['proficiencies']['data'] ?? [])
                ->pluck('slug')
                ->toArray(),
            'proficiency_count' => count($snapshot['proficiencies']['data'] ?? []),

            // Features by source
            'features_by_source' => $this->groupBySource($snapshot['features']['data'] ?? []),
            'feature_count' => count($snapshot['features']['data'] ?? []),

            // Equipment
            'equipment_slugs' => collect($snapshot['equipment']['data'] ?? [])
                ->pluck('item.slug')
                ->toArray(),
            'equipment_count' => count($snapshot['equipment']['data'] ?? []),

            // Pending choices
            'pending_choice_types' => collect($snapshot['pending_choices']['data'] ?? [])
                ->pluck('type')
                ->unique()
                ->values()
                ->toArray(),
            'pending_choice_count' => count($snapshot['pending_choices']['data'] ?? []),

            // Computed stats
            'hit_points' => [
                'max' => $character['max_hit_points'] ?? null,
                'current' => $character['current_hit_points'] ?? null,
            ],
            'armor_class' => $character['armor_class'] ?? null,
            'proficiency_bonus' => $character['proficiency_bonus'] ?? null,

            // Spellcasting
            'spellcasting' => $stats['spellcasting'] ?? null,
            'spell_slots' => $stats['spell_slots'] ?? null,

            // Validation
            'is_complete' => $character['is_complete'] ?? false,
            'validation_status' => $character['validation_status'] ?? [],
        ];
    }

    /**
     * Group items by their 'source' field.
     */
    private function groupBySource(array $items): array
    {
        return collect($items)
            ->groupBy('source')
            ->map(fn ($group) => $group->pluck('slug')->toArray())
            ->toArray();
    }

    /**
     * Compare two snapshots and return differences.
     */
    public static function diff(array $before, array $after): array
    {
        $differences = [];

        $beforeDerived = $before['derived'] ?? [];
        $afterDerived = $after['derived'] ?? [];

        // Compare each derived field
        foreach ($afterDerived as $key => $afterValue) {
            $beforeValue = $beforeDerived[$key] ?? null;

            if ($beforeValue !== $afterValue) {
                $differences[$key] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        return $differences;
    }

    /**
     * Create a minimal snapshot for a newly created character (before full data exists).
     */
    public function captureMinimal(int|string $characterId): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'character_id' => $characterId,
            'character' => $this->makeRequest('GET', "/api/v1/characters/{$characterId}"),
            'stats' => [],
            'pending_choices' => [],
            'spells' => [],
            'equipment' => [],
            'languages' => [],
            'proficiencies' => [],
            'features' => [],
            'derived' => [],
        ];
    }
}
