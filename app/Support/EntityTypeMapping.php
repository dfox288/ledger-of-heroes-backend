<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Race;

/**
 * Shared mapping between entity model classes and their portable type names.
 *
 * Used for character export/import to ensure consistent entity type resolution.
 */
final class EntityTypeMapping
{
    /**
     * Map from model class to portable type name.
     */
    public const CLASS_TO_TYPE = [
        Race::class => 'race',
        Background::class => 'background',
        CharacterClass::class => 'class',
    ];

    /**
     * Map from portable type name to model class.
     */
    public const TYPE_TO_CLASS = [
        'race' => Race::class,
        'background' => Background::class,
        'class' => CharacterClass::class,
    ];

    /**
     * Get the portable type name for a model class.
     */
    public static function getTypeForClass(string $class): ?string
    {
        return self::CLASS_TO_TYPE[$class] ?? null;
    }

    /**
     * Get the model class for a portable type name.
     */
    public static function getClassForType(string $type): ?string
    {
        return self::TYPE_TO_CLASS[$type] ?? null;
    }
}
