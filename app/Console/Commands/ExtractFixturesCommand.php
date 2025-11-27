<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExtractFixturesCommand extends Command
{
    protected $signature = 'fixtures:extract
                            {entity : Entity type to extract (spells, monsters, classes, races, items, feats, backgrounds, optionalfeatures, all)}
                            {--output=tests/fixtures : Output directory}
                            {--analyze-tests : Analyze test files for referenced entities}
                            {--limit=100 : Maximum entities per type}';

    protected $description = 'Extract fixture data from database for test seeding';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $output = $this->option('output');

        if (! File::isDirectory(base_path($output))) {
            File::makeDirectory(base_path($output), 0755, true);
        }

        $entities = $entity === 'all'
            ? ['spells', 'monsters', 'classes', 'races', 'items', 'feats', 'backgrounds', 'optionalfeatures']
            : [$entity];

        foreach ($entities as $entityType) {
            $this->extractEntity($entityType, $output);
        }

        return self::SUCCESS;
    }

    protected function extractEntity(string $entity, string $output): void
    {
        $this->info("Extracting {$entity}...");

        $extractor = $this->getExtractor($entity);

        if (! $extractor) {
            $this->error("Unknown entity type: {$entity}");

            return;
        }

        $data = $extractor();
        $path = base_path("{$output}/entities/{$entity}.json");

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('  Extracted '.count($data)." {$entity} to {$path}");
    }

    protected function getExtractor(string $entity): ?\Closure
    {
        return match ($entity) {
            'spells' => fn () => $this->extractSpells(),
            'monsters' => fn () => $this->extractMonsters(),
            'classes' => fn () => $this->extractClasses(),
            'races' => fn () => $this->extractRaces(),
            'items' => fn () => $this->extractItems(),
            'feats' => fn () => $this->extractFeats(),
            'backgrounds' => fn () => $this->extractBackgrounds(),
            'optionalfeatures' => fn () => $this->extractOptionalFeatures(),
            default => null,
        };
    }

    // Placeholder extractors - will be implemented in subsequent tasks
    protected function extractSpells(): array
    {
        $limit = (int) $this->option('limit');

        // Get coverage-based selection:
        // 1. One spell per level (0-9)
        // 2. One spell per school
        // 3. Concentration and ritual variants
        // 4. Additional random spells up to limit

        $spellIds = collect();

        // One per level
        foreach (range(0, 9) as $level) {
            $spell = \App\Models\Spell::where('level', $level)->first();
            if ($spell) {
                $spellIds->push($spell->id);
            }
        }

        // One per school
        \App\Models\SpellSchool::all()->each(function ($school) use ($spellIds) {
            $spell = \App\Models\Spell::where('spell_school_id', $school->id)
                ->whereNotIn('id', $spellIds->toArray())
                ->first();
            if ($spell) {
                $spellIds->push($spell->id);
            }
        });

        // Concentration spells
        $concentration = \App\Models\Spell::where('needs_concentration', true)
            ->whereNotIn('id', $spellIds->toArray())
            ->take(3)
            ->pluck('id');
        $spellIds = $spellIds->merge($concentration);

        // Ritual spells
        $ritual = \App\Models\Spell::where('is_ritual', true)
            ->whereNotIn('id', $spellIds->toArray())
            ->take(3)
            ->pluck('id');
        $spellIds = $spellIds->merge($ritual);

        // Fill remaining with random spells
        $remaining = $limit - $spellIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Spell::whereNotIn('id', $spellIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $spellIds = $spellIds->merge($additional);
        }

        // Load full models with relationships
        $spells = \App\Models\Spell::whereIn('id', $spellIds->unique())
            ->with(['spellSchool', 'classes', 'sources.source', 'effects.damageType'])
            ->get();

        return $spells->map(fn ($spell) => $this->formatSpell($spell))->toArray();
    }

    protected function formatSpell(\App\Models\Spell $spell): array
    {
        return [
            'name' => $spell->name,
            'slug' => $spell->slug,
            'level' => $spell->level,
            'school' => $spell->spellSchool->code,
            'casting_time' => $spell->casting_time,
            'range' => $spell->range,
            'components' => $spell->components,
            'material_components' => $spell->material_components,
            'duration' => $spell->duration,
            'needs_concentration' => $spell->needs_concentration,
            'is_ritual' => $spell->is_ritual,
            'description' => $spell->description,
            'higher_levels' => $spell->higher_levels,
            'classes' => $spell->classes->pluck('slug')->toArray(),
            'damage_types' => $spell->effects
                ->filter(fn ($e) => $e->damageType)
                ->pluck('damageType.code')
                ->unique()
                ->values()
                ->toArray(),
            'sources' => $spell->sources->map(function ($entitySource) {
                return [
                    'code' => $entitySource->source->code,
                    'pages' => $entitySource->pages,
                ];
            })->toArray(),
        ];
    }

    protected function extractMonsters(): array
    {
        $limit = (int) $this->option('limit');
        $monsterIds = collect();

        // Coverage-based: one per CR tier
        $crTiers = [0, 0.125, 0.25, 0.5, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30];

        foreach ($crTiers as $cr) {
            $monster = \App\Models\Monster::where('challenge_rating', $cr)->first();
            if ($monster) {
                $monsterIds->push($monster->id);
            }
        }

        // One per size
        \App\Models\Size::all()->each(function ($size) use ($monsterIds) {
            $monster = \App\Models\Monster::where('size_id', $size->id)
                ->whereNotIn('id', $monsterIds->toArray())
                ->first();
            if ($monster) {
                $monsterIds->push($monster->id);
            }
        });

        // One per type
        \App\Models\Monster::distinct('type')->pluck('type')->each(function ($type) use ($monsterIds) {
            $monster = \App\Models\Monster::where('type', $type)
                ->whereNotIn('id', $monsterIds->toArray())
                ->first();
            if ($monster) {
                $monsterIds->push($monster->id);
            }
        });

        // Fill remaining
        $remaining = $limit - $monsterIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Monster::whereNotIn('id', $monsterIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $monsterIds = $monsterIds->merge($additional);
        }

        $monsters = \App\Models\Monster::whereIn('id', $monsterIds->unique())
            ->with(['size', 'sources.source', 'modifiers.damageType'])
            ->get();

        return $monsters->map(fn ($m) => $this->formatMonster($m))->toArray();
    }

    protected function formatMonster(\App\Models\Monster $monster): array
    {
        // Group modifiers by category
        $damageVulnerabilities = $monster->modifiers
            ->where('modifier_category', 'damage_vulnerability')
            ->pluck('damageType.slug')
            ->filter()
            ->values()
            ->toArray();

        $damageResistances = $monster->modifiers
            ->where('modifier_category', 'damage_resistance')
            ->pluck('damageType.slug')
            ->filter()
            ->values()
            ->toArray();

        $damageImmunities = $monster->modifiers
            ->where('modifier_category', 'damage_immunity')
            ->pluck('damageType.slug')
            ->filter()
            ->values()
            ->toArray();

        $conditionImmunities = $monster->modifiers
            ->where('modifier_category', 'condition_immunity')
            ->pluck('value')
            ->filter()
            ->values()
            ->toArray();

        return [
            'name' => $monster->name,
            'slug' => $monster->slug,
            'size' => $monster->size->code,
            'type' => $monster->type,
            'alignment' => $monster->alignment,
            'armor_class' => $monster->armor_class,
            'armor_type' => $monster->armor_type,
            'hit_points' => $monster->hit_points_average,
            'hit_dice' => $monster->hit_dice,
            'speed_walk' => $monster->speed_walk,
            'speed_fly' => $monster->speed_fly,
            'speed_swim' => $monster->speed_swim,
            'speed_burrow' => $monster->speed_burrow,
            'speed_climb' => $monster->speed_climb,
            'can_hover' => $monster->can_hover,
            'strength' => $monster->strength,
            'dexterity' => $monster->dexterity,
            'constitution' => $monster->constitution,
            'intelligence' => $monster->intelligence,
            'wisdom' => $monster->wisdom,
            'charisma' => $monster->charisma,
            'challenge_rating' => $monster->challenge_rating,
            'experience_points' => $monster->experience_points,
            'passive_perception' => $monster->passive_perception,
            'damage_vulnerabilities' => $damageVulnerabilities,
            'damage_resistances' => $damageResistances,
            'damage_immunities' => $damageImmunities,
            'condition_immunities' => $conditionImmunities,
            'description' => $monster->description,
            'is_npc' => $monster->is_npc,
            'source' => $monster->sources->first()?->source->code,
            'pages' => $monster->sources->first()?->pages,
        ];
    }

    protected function extractClasses(): array
    {
        $limit = (int) $this->option('limit');
        $classIds = collect();

        // Coverage-based selection:
        // 1. All base classes (no parent)
        // 2. One subclass per base class (if available)
        // 3. One per hit die value
        // 4. Spellcasters vs non-spellcasters

        // All base classes
        $baseClasses = \App\Models\CharacterClass::whereNull('parent_class_id')
            ->pluck('id');
        $classIds = $classIds->merge($baseClasses);

        // One subclass per base class
        foreach ($baseClasses as $baseClassId) {
            $subclass = \App\Models\CharacterClass::where('parent_class_id', $baseClassId)
                ->whereNotIn('id', $classIds->toArray())
                ->first();
            if ($subclass) {
                $classIds->push($subclass->id);
            }
        }

        // One per hit die value
        foreach ([6, 8, 10, 12] as $hitDie) {
            $class = \App\Models\CharacterClass::where('hit_die', $hitDie)
                ->whereNotIn('id', $classIds->toArray())
                ->first();
            if ($class) {
                $classIds->push($class->id);
            }
        }

        // Spellcasters
        $spellcasters = \App\Models\CharacterClass::whereNotNull('spellcasting_ability_id')
            ->whereNotIn('id', $classIds->toArray())
            ->take(3)
            ->pluck('id');
        $classIds = $classIds->merge($spellcasters);

        // Fill remaining with random classes
        $remaining = $limit - $classIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\CharacterClass::whereNotIn('id', $classIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $classIds = $classIds->merge($additional);
        }

        // Load full models with relationships
        $classes = \App\Models\CharacterClass::whereIn('id', $classIds->unique())
            ->with([
                'spellcastingAbility',
                'parentClass',
                'sources.source',
                'proficiencies.skill',
                'proficiencies.abilityScore',
                'proficiencies.proficiencyType',
                'proficiencies.item',
            ])
            ->get();

        return $classes->map(fn ($class) => $this->formatClass($class))->toArray();
    }

    protected function formatClass(\App\Models\CharacterClass $class): array
    {
        return [
            'name' => $class->name,
            'slug' => $class->slug,
            'hit_die' => $class->hit_die,
            'description' => $class->description,
            'primary_ability' => $class->primary_ability,
            'spellcasting_ability' => $class->spellcastingAbility?->code,
            'parent_class_slug' => $class->parentClass?->slug,
            'proficiencies' => $class->proficiencies->map(function ($prof) {
                return [
                    'proficiency_type' => $prof->proficiency_type,
                    'proficiency_subcategory' => $prof->proficiency_subcategory,
                    'proficiency_name' => $prof->proficiency_name,
                    'skill_code' => $prof->skill?->code,
                    'ability_code' => $prof->abilityScore?->code,
                    'item_slug' => $prof->item?->slug,
                    'grants' => $prof->grants,
                    'is_choice' => $prof->is_choice,
                    'choice_group' => $prof->choice_group,
                    'choice_option' => $prof->choice_option,
                    'quantity' => $prof->quantity,
                    'level' => $prof->level,
                ];
            })->toArray(),
            'source' => $class->sources->first()?->source->code,
            'pages' => $class->sources->first()?->pages,
        ];
    }

    protected function extractRaces(): array
    {
        $limit = (int) $this->option('limit');
        $raceIds = collect();

        // Coverage-based selection:
        // 1. One per size
        // 2. Races with subraces
        // 3. Subraces themselves
        // 4. Fill remaining

        // One per size
        \App\Models\Size::all()->each(function ($size) use ($raceIds) {
            $race = \App\Models\Race::where('size_id', $size->id)
                ->whereNull('parent_race_id')
                ->first();
            if ($race) {
                $raceIds->push($race->id);
            }
        });

        // Races with subraces (base races that have children)
        $racesWithSubraces = \App\Models\Race::whereNull('parent_race_id')
            ->whereHas('subraces')
            ->whereNotIn('id', $raceIds->toArray())
            ->take(3)
            ->pluck('id');
        $raceIds = $raceIds->merge($racesWithSubraces);

        // Add at least one subrace for each race with subraces
        foreach ($racesWithSubraces as $baseRaceId) {
            $subrace = \App\Models\Race::where('parent_race_id', $baseRaceId)
                ->whereNotIn('id', $raceIds->toArray())
                ->first();
            if ($subrace) {
                $raceIds->push($subrace->id);
            }
        }

        // Fill remaining with random races
        $remaining = $limit - $raceIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Race::whereNotIn('id', $raceIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $raceIds = $raceIds->merge($additional);
        }

        // Load full models with relationships
        $races = \App\Models\Race::whereIn('id', $raceIds->unique())
            ->with([
                'size',
                'parent',
                'sources.source',
                'traits',
                'modifiers.abilityScore',
                'modifiers.skill',
                'languages',
                'proficiencies',
            ])
            ->get();

        return $races->map(fn ($race) => $this->formatRace($race))->toArray();
    }

    protected function formatRace(\App\Models\Race $race): array
    {
        // Extract ability score bonuses
        $abilityBonuses = $race->modifiers
            ->where('modifier_category', 'ability_score')
            ->map(function ($modifier) {
                return [
                    'ability' => $modifier->abilityScore?->code,
                    'bonus' => (int) $modifier->value,
                    'is_choice' => $modifier->is_choice ?? false,
                ];
            })
            ->values()
            ->toArray();

        // Extract traits
        $traits = $race->traits->map(function ($trait) {
            return [
                'name' => $trait->name,
                'category' => $trait->category,
                'description' => $trait->description,
            ];
        })->values()->toArray();

        return [
            'name' => $race->name,
            'slug' => $race->slug,
            'size' => $race->size?->code,
            'speed' => $race->speed,
            'parent_race_slug' => $race->parent?->slug,
            'ability_bonuses' => $abilityBonuses,
            'traits' => $traits,
            'source' => $race->sources->first()?->source->code,
            'pages' => $race->sources->first()?->pages,
        ];
    }

    protected function extractItems(): array
    {
        $limit = (int) $this->option('limit');
        $itemIds = collect();

        // Coverage-based selection:
        // 1. One per rarity (common, uncommon, rare, very rare, legendary, artifact)
        // 2. One per item type
        // 3. Magical vs mundane variants
        // 4. Items with properties, attunement requirements, charges
        // 5. Fill remaining with random items

        $rarities = ['common', 'uncommon', 'rare', 'very rare', 'legendary', 'artifact'];

        // One per rarity
        foreach ($rarities as $rarity) {
            $item = \App\Models\Item::where('rarity', $rarity)->first();
            if ($item) {
                $itemIds->push($item->id);
            }
        }

        // One per item type
        \App\Models\ItemType::all()->each(function ($type) use ($itemIds) {
            $item = \App\Models\Item::where('item_type_id', $type->id)
                ->whereNotIn('id', $itemIds->toArray())
                ->first();
            if ($item) {
                $itemIds->push($item->id);
            }
        });

        // Magical items (not already included)
        $magicalItems = \App\Models\Item::where('is_magic', true)
            ->whereNotIn('id', $itemIds->toArray())
            ->take(5)
            ->pluck('id');
        $itemIds = $itemIds->merge($magicalItems);

        // Mundane items (not already included)
        $mundaneItems = \App\Models\Item::where('is_magic', false)
            ->whereNotIn('id', $itemIds->toArray())
            ->take(5)
            ->pluck('id');
        $itemIds = $itemIds->merge($mundaneItems);

        // Items requiring attunement
        $attunementItems = \App\Models\Item::where('requires_attunement', true)
            ->whereNotIn('id', $itemIds->toArray())
            ->take(3)
            ->pluck('id');
        $itemIds = $itemIds->merge($attunementItems);

        // Items with charges
        $chargedItems = \App\Models\Item::whereNotNull('charges_max')
            ->whereNotIn('id', $itemIds->toArray())
            ->take(3)
            ->pluck('id');
        $itemIds = $itemIds->merge($chargedItems);

        // Weapons with damage dice
        $weapons = \App\Models\Item::whereNotNull('damage_dice')
            ->whereNotIn('id', $itemIds->toArray())
            ->take(5)
            ->pluck('id');
        $itemIds = $itemIds->merge($weapons);

        // Armor with AC
        $armor = \App\Models\Item::whereNotNull('armor_class')
            ->whereNotIn('id', $itemIds->toArray())
            ->take(3)
            ->pluck('id');
        $itemIds = $itemIds->merge($armor);

        // Fill remaining with random items
        $remaining = $limit - $itemIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Item::whereNotIn('id', $itemIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $itemIds = $itemIds->merge($additional);
        }

        // Load full models with relationships
        $items = \App\Models\Item::whereIn('id', $itemIds->unique())
            ->with([
                'itemType',
                'damageType',
                'properties',
                'sources.source',
            ])
            ->get();

        return $items->map(fn ($item) => $this->formatItem($item))->toArray();
    }

    protected function formatItem(\App\Models\Item $item): array
    {
        return [
            'name' => $item->name,
            'slug' => $item->slug,
            'item_type' => $item->itemType?->code,
            'detail' => $item->detail,
            'rarity' => $item->rarity,
            'requires_attunement' => $item->requires_attunement,
            'is_magic' => $item->is_magic,
            'cost_cp' => $item->cost_cp,
            'weight' => $item->weight ? (float) $item->weight : null,
            'description' => $item->description,
            // Weapon-specific fields
            'damage_dice' => $item->damage_dice,
            'versatile_damage' => $item->versatile_damage,
            'damage_type' => $item->damageType?->code,
            'range_normal' => $item->range_normal,
            'range_long' => $item->range_long,
            // Armor-specific fields
            'armor_class' => $item->armor_class,
            'strength_requirement' => $item->strength_requirement,
            'stealth_disadvantage' => $item->stealth_disadvantage,
            // Charge mechanics (magic items)
            'charges_max' => $item->charges_max,
            'recharge_formula' => $item->recharge_formula,
            'recharge_timing' => $item->recharge_timing,
            // Properties and relationships
            'properties' => $item->properties->pluck('code')->toArray(),
            'source' => $item->sources->first()?->source->code,
            'pages' => $item->sources->first()?->pages,
        ];
    }

    protected function extractFeats(): array
    {
        $limit = (int) $this->option('limit');
        $featIds = collect();

        // Coverage-based selection:
        // 1. Feats with prerequisites (via prerequisites_text or prerequisites relationship)
        // 2. Feats without prerequisites
        // 3. Feats with ability score improvements
        // 4. Feats with multiple ability score choices
        // 5. Fill remaining with random feats

        // Feats with ability score improvements
        $featsWithASI = \App\Models\Feat::whereHas('modifiers', function ($q) {
            $q->where('modifier_category', 'ability_score');
        })
            ->take(10)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithASI);

        // Feats with prerequisites (via text)
        $featsWithPrereqText = \App\Models\Feat::whereNotNull('prerequisites_text')
            ->whereNotIn('id', $featIds->toArray())
            ->take(5)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithPrereqText);

        // Feats with prerequisites (via relationships)
        $featsWithPrereqRelation = \App\Models\Feat::has('prerequisites')
            ->whereNotIn('id', $featIds->toArray())
            ->take(5)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithPrereqRelation);

        // Feats without prerequisites
        $featsWithoutPrereqs = \App\Models\Feat::whereNull('prerequisites_text')
            ->doesntHave('prerequisites')
            ->whereNotIn('id', $featIds->toArray())
            ->take(5)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithoutPrereqs);

        // Feats with ability score choices (is_choice = true)
        $featsWithChoices = \App\Models\Feat::whereHas('modifiers', function ($q) {
            $q->where('modifier_category', 'ability_score')
                ->where('is_choice', true);
        })
            ->whereNotIn('id', $featIds->toArray())
            ->take(5)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithChoices);

        // Feats with proficiencies
        $featsWithProficiencies = \App\Models\Feat::has('proficiencies')
            ->whereNotIn('id', $featIds->toArray())
            ->take(5)
            ->pluck('id');
        $featIds = $featIds->merge($featsWithProficiencies);

        // Fill remaining with random feats
        $remaining = $limit - $featIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Feat::whereNotIn('id', $featIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $featIds = $featIds->merge($additional);
        }

        // Load full models with relationships
        $feats = \App\Models\Feat::whereIn('id', $featIds->unique())
            ->with([
                'sources.source',
                'prerequisites.prerequisite',
                'modifiers.abilityScore',
                'proficiencies',
            ])
            ->get();

        return $feats->map(fn ($feat) => $this->formatFeat($feat))->toArray();
    }

    protected function formatFeat(\App\Models\Feat $feat): array
    {
        // Extract prerequisites
        $prerequisites = $feat->prerequisites->map(function ($prereq) {
            $prerequisiteData = [
                'type' => class_basename($prereq->prerequisite_type),
            ];

            // Add type-specific data
            if ($prereq->prerequisite) {
                $prerequisiteData['value'] = match (class_basename($prereq->prerequisite_type)) {
                    'AbilityScore' => $prereq->prerequisite->code,
                    'Race' => $prereq->prerequisite->slug,
                    'ProficiencyType' => $prereq->prerequisite->slug,
                    default => $prereq->prerequisite->name ?? $prereq->prerequisite->code ?? null,
                };
            }

            if ($prereq->minimum_value) {
                $prerequisiteData['minimum_value'] = $prereq->minimum_value;
            }

            if ($prereq->description) {
                $prerequisiteData['description'] = $prereq->description;
            }

            return $prerequisiteData;
        })->values()->toArray();

        // Extract ability score improvements
        $abilityScoreImprovements = $feat->modifiers
            ->where('modifier_category', 'ability_score')
            ->map(function ($modifier) {
                return [
                    'ability' => $modifier->abilityScore?->code,
                    'value' => (int) $modifier->value,
                    'is_choice' => $modifier->is_choice ?? false,
                    'choice_count' => $modifier->choice_count,
                ];
            })
            ->values()
            ->toArray();

        return [
            'name' => $feat->name,
            'slug' => $feat->slug,
            'description' => $feat->description,
            'prerequisites_text' => $feat->prerequisites_text,
            'prerequisites' => $prerequisites,
            'ability_score_improvements' => $abilityScoreImprovements,
            'source' => $feat->sources->first()?->source->code,
            'pages' => $feat->sources->first()?->pages,
        ];
    }

    protected function extractBackgrounds(): array
    {
        return [];
    }

    protected function extractOptionalFeatures(): array
    {
        return [];
    }
}
