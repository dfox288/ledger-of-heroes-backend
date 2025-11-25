<?php
/**
 * ASI Data Verification Script
 *
 * Run with: docker compose exec php php docs/verify-asi-data.php
 *
 * Purpose: Verify that ASI (Ability Score Improvement) tracking data exists
 * in the entity_modifiers table and is correctly structured for character builder.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CharacterClass;
use Illuminate\Support\Facades\DB;

echo "\n=== ASI DATA VERIFICATION REPORT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Verify entity_modifiers table exists
echo "1. CHECKING TABLE STRUCTURE\n";
echo str_repeat("-", 60) . "\n";

try {
    $tableExists = DB::select("SHOW TABLES LIKE 'entity_modifiers'");
    if (empty($tableExists)) {
        echo "❌ CRITICAL: entity_modifiers table does not exist!\n";
        exit(1);
    }
    echo "✅ Table 'entity_modifiers' exists\n";

    // Check for level column
    $columns = DB::select("SHOW COLUMNS FROM entity_modifiers");
    $hasLevelColumn = collect($columns)->pluck('Field')->contains('level');

    if (!$hasLevelColumn) {
        echo "❌ CRITICAL: 'level' column missing from entity_modifiers table!\n";
        exit(1);
    }
    echo "✅ Column 'level' exists\n\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Check ASI data for base classes
echo "2. BASE CLASS ASI LEVELS\n";
echo str_repeat("-", 60) . "\n";

$baseClasses = CharacterClass::whereNull('parent_class_id')
    ->orderBy('name')
    ->get();

$asiReport = [];

foreach ($baseClasses as $class) {
    $asiLevels = $class->modifiers()
        ->where('modifier_category', 'ability_score')
        ->whereNotNull('level')
        ->orderBy('level')
        ->pluck('level')
        ->toArray();

    $asiCount = count($asiLevels);
    $asiReport[] = [
        'name' => $class->name,
        'slug' => $class->slug,
        'levels' => $asiLevels,
        'count' => $asiCount,
    ];
}

$hasData = false;
foreach ($asiReport as $report) {
    if ($report['count'] > 0) {
        $hasData = true;
        echo sprintf(
            "✅ %-20s [%d ASIs]: %s\n",
            $report['name'],
            $report['count'],
            implode(', ', $report['levels'])
        );
    } else {
        echo sprintf(
            "⚠️  %-20s [0 ASIs]: No ASI data found\n",
            $report['name']
        );
    }
}

if (!$hasData) {
    echo "\n❌ CRITICAL: No ASI data found for any class!\n";
    echo "   This data must be imported before building character system.\n\n";
} else {
    echo "\n";
}

// 3. Verify Fighter specifically (should have 7 ASIs)
echo "3. FIGHTER ASI VERIFICATION (Expected: 7 ASIs)\n";
echo str_repeat("-", 60) . "\n";

$fighter = CharacterClass::where('slug', 'fighter')->first();

if (!$fighter) {
    echo "❌ ERROR: Fighter class not found in database\n\n";
} else {
    $fighterAsis = $fighter->modifiers()
        ->where('modifier_category', 'ability_score')
        ->whereNotNull('level')
        ->orderBy('level')
        ->get();

    echo "Fighter ASI details:\n";

    if ($fighterAsis->isEmpty()) {
        echo "❌ No ASI data found for Fighter\n\n";
    } else {
        foreach ($fighterAsis as $asi) {
            echo sprintf(
                "  Level %2d: value=%s, ability_score_id=%s, is_choice=%s\n",
                $asi->level,
                $asi->value,
                $asi->ability_score_id ?? 'NULL',
                $asi->is_choice ? 'true' : 'false'
            );
        }

        $expectedLevels = [4, 6, 8, 12, 14, 16, 19];
        $actualLevels = $fighterAsis->pluck('level')->toArray();

        if ($actualLevels === $expectedLevels) {
            echo "\n✅ Fighter has correct 7 ASI levels: [4, 6, 8, 12, 14, 16, 19]\n\n";
        } else {
            echo "\n⚠️  Fighter ASI levels don't match expected:\n";
            echo "   Expected: [" . implode(', ', $expectedLevels) . "]\n";
            echo "   Actual:   [" . implode(', ', $actualLevels) . "]\n\n";
        }
    }
}

// 4. Check modifier structure for ASIs
echo "4. ASI MODIFIER STRUCTURE VALIDATION\n";
echo str_repeat("-", 60) . "\n";

$sampleAsi = DB::table('entity_modifiers')
    ->where('modifier_category', 'ability_score')
    ->whereNotNull('level')
    ->first();

if (!$sampleAsi) {
    echo "❌ No ASI modifiers found in database\n\n";
} else {
    echo "Sample ASI modifier structure:\n";
    echo "  reference_type: {$sampleAsi->reference_type}\n";
    echo "  reference_id: {$sampleAsi->reference_id}\n";
    echo "  modifier_category: {$sampleAsi->modifier_category}\n";
    echo "  level: {$sampleAsi->level}\n";
    echo "  value: {$sampleAsi->value}\n";
    echo "  ability_score_id: " . ($sampleAsi->ability_score_id ?? 'NULL') . "\n";
    echo "  is_choice: " . ($sampleAsi->is_choice ?? 'NULL') . "\n";

    // Validate structure
    $issues = [];

    if ($sampleAsi->reference_type !== 'App\\Models\\CharacterClass') {
        $issues[] = "reference_type should be 'App\\Models\\CharacterClass'";
    }

    if ($sampleAsi->modifier_category !== 'ability_score') {
        $issues[] = "modifier_category should be 'ability_score'";
    }

    if (!is_null($sampleAsi->ability_score_id)) {
        $issues[] = "ability_score_id should be NULL (player chooses)";
    }

    if (empty($issues)) {
        echo "\n✅ ASI structure is correct\n\n";
    } else {
        echo "\n⚠️  Potential issues:\n";
        foreach ($issues as $issue) {
            echo "   - {$issue}\n";
        }
        echo "\n";
    }
}

// 5. Summary and recommendations
echo "5. SUMMARY & RECOMMENDATIONS\n";
echo str_repeat("-", 60) . "\n";

$totalClasses = $baseClasses->count();
$classesWithAsi = collect($asiReport)->where('count', '>', 0)->count();
$totalAsis = collect($asiReport)->sum('count');

echo "Statistics:\n";
echo "  - Total base classes: {$totalClasses}\n";
echo "  - Classes with ASI data: {$classesWithAsi}\n";
echo "  - Total ASI records: {$totalAsis}\n\n";

if ($totalAsis === 0) {
    echo "❌ CRITICAL: No ASI data in database!\n";
    echo "\nAction Required:\n";
    echo "  1. Check if class XML files contain ASI/feat data\n";
    echo "  2. Verify ClassImporter imports modifier data at correct levels\n";
    echo "  3. Re-import class data with ASI support\n\n";
    exit(1);
} elseif ($classesWithAsi < $totalClasses / 2) {
    echo "⚠️  WARNING: Less than half of classes have ASI data\n";
    echo "\nRecommendation:\n";
    echo "  - Verify all class XML files were imported\n";
    echo "  - Check import logs for errors\n\n";
} else {
    echo "✅ ASI data looks good - ready for character builder!\n\n";
    echo "Next Steps:\n";
    echo "  1. Document these ASI levels in CHARACTER-BUILDER-ANALYSIS.md\n";
    echo "  2. Create character_ability_scores table migration\n";
    echo "  3. Build AbilityScoreService with ASI application logic\n\n";
}

echo "=== END REPORT ===\n\n";
