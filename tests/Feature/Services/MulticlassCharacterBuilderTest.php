<?php

declare(strict_types=1);

use App\Services\MulticlassCharacterBuilder;

/**
 * Tests for MulticlassCharacterBuilder.
 *
 * Note: The `build()` method requires real fixture data (races, classes, backgrounds)
 * and is tested manually via `php artisan test:multiclass-combinations`.
 * Only the parsing logic is unit-tested here.
 */
describe('MulticlassCharacterBuilder', function () {
    describe('validateClassLevels', function () {
        it('throws exception when total level exceeds 20', function () {
            $builder = app(MulticlassCharacterBuilder::class);

            $classLevels = [
                ['class' => 'phb:wizard', 'level' => 15],
                ['class' => 'phb:cleric', 'level' => 10],
            ];

            $builder->build($classLevels, seed: 42);
        })->throws(InvalidArgumentException::class, 'Total level cannot exceed 20');

        it('throws exception when no classes specified', function () {
            $builder = app(MulticlassCharacterBuilder::class);

            $builder->build([], seed: 42);
        })->throws(InvalidArgumentException::class, 'At least one class must be specified');

        it('throws exception when class level is zero', function () {
            $builder = app(MulticlassCharacterBuilder::class);

            $classLevels = [
                ['class' => 'phb:wizard', 'level' => 0],
            ];

            $builder->build($classLevels, seed: 42);
        })->throws(InvalidArgumentException::class, 'Class level must be at least 1');

        it('throws exception when duplicate class specified', function () {
            $builder = app(MulticlassCharacterBuilder::class);

            $classLevels = [
                ['class' => 'phb:wizard', 'level' => 5],
                ['class' => 'wizard', 'level' => 5],  // Same class with shorthand
            ];

            $builder->build($classLevels, seed: 42);
        })->throws(InvalidArgumentException::class, 'Duplicate class specified');
    });

    describe('parseClassLevels', function () {
        it('parses simple combination string', function () {
            $result = MulticlassCharacterBuilder::parseClassLevels('wizard:5,cleric:5');

            expect($result)->toBe([
                ['class' => 'phb:wizard', 'level' => 5],
                ['class' => 'phb:cleric', 'level' => 5],
            ]);
        });

        it('parses triple class combination', function () {
            $result = MulticlassCharacterBuilder::parseClassLevels('fighter:6,rogue:7,wizard:7');

            expect($result)->toBe([
                ['class' => 'phb:fighter', 'level' => 6],
                ['class' => 'phb:rogue', 'level' => 7],
                ['class' => 'phb:wizard', 'level' => 7],
            ]);
        });

        it('handles full slug with source prefix', function () {
            $result = MulticlassCharacterBuilder::parseClassLevels('erlw:artificer:5');

            expect($result)->toBe([
                ['class' => 'erlw:artificer', 'level' => 5],
            ]);
        });

        it('handles mixed shorthand and full slugs', function () {
            $result = MulticlassCharacterBuilder::parseClassLevels('wizard:5,erlw:artificer:5');

            expect($result)->toBe([
                ['class' => 'phb:wizard', 'level' => 5],
                ['class' => 'erlw:artificer', 'level' => 5],
            ]);
        });
    });
});
