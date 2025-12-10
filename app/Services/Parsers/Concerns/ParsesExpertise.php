<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing expertise/double proficiency bonus from description text.
 *
 * Used by: RaceXmlParser (Stonecunning, Artificer's Lore), FeatXmlParser
 *
 * Detects patterns like:
 * - "add double your proficiency bonus"
 * - "add twice your proficiency bonus"
 * - "proficiency bonus is doubled"
 *
 * Stores in entity_modifiers with modifier_category = 'expertise'.
 */
trait ParsesExpertise
{
    private const EXPERTISE_MODIFIER_CATEGORY = 'expertise';

    /**
     * Build an expertise modifier array with consistent structure.
     */
    private function buildExpertiseModifier(
        ?string $skillName,
        ?string $toolName,
        ?string $abilityScoreName,
        bool $grantsProficiency,
        ?string $condition
    ): array {
        return [
            'modifier_category' => self::EXPERTISE_MODIFIER_CATEGORY,
            'skill_name' => $skillName ?: null,
            'tool_name' => $toolName,
            'ability_score_name' => $abilityScoreName ? trim($abilityScoreName) : null,
            'grants_proficiency' => $grantsProficiency,
            'condition' => $condition,
        ];
    }

    /**
     * Check if a modifier with the same skill already exists.
     *
     * Note: We only check skill_name, not condition, because later patterns (without
     * condition extraction) might match the same skill. The first pattern match
     * (with condition) is preferred as it has more specific information.
     */
    private function expertiseModifierExists(array $modifiers, ?string $skillName, ?string $condition = null): bool
    {
        return collect($modifiers)->contains(function ($m) use ($skillName) {
            return $m['skill_name'] === $skillName;
        });
    }

    /**
     * Parse expertise/double proficiency patterns from description text.
     *
     * Detects patterns like:
     * - "add double your proficiency bonus to the check" (Stonecunning)
     * - "add twice your proficiency bonus" (Artificer's Lore)
     * - "proficiency bonus is doubled for any ability check"
     * - "have expertise in the X skill"
     *
     * Output structure matches entity_modifiers table:
     * - modifier_category: 'skill_expertise'
     * - skill_name: The skill name (to be resolved to skill_id)
     * - value: 'double'
     * - condition: Conditional text if any
     *
     * @return array<int, array{
     *     modifier_category: string,
     *     skill_name: string|null,
     *     tool_name: string|null,
     *     ability_score_name: string|null,
     *     grants_proficiency: bool,
     *     condition: string|null
     * }>
     */
    protected function parseExpertise(string $text): array
    {
        $modifiers = [];

        // Check if text mentions "considered proficient" - grants proficiency too
        $grantsProficiency = (bool) preg_match('/considered\s+proficient/i', $text);

        // Pattern 1: "Ability (Skill) check [condition] ... add double/twice your proficiency bonus"
        // Captures condition text between "related to/when/while" and "you are/you can/add double"
        $abilityCheckPattern = '/([A-Z][a-z]+)\s*\(([^)]+)\)\s+checks?\s*(?:(related to|when|while)\s+(.+?))?,?\s*(?:you\s+(?:are|can)|add\s+(?:double|twice)\s+your\s+proficiency\s+bonus|proficiency\s+bonus\s+is\s+doubled)/i';

        if (preg_match_all($abilityCheckPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $abilityScore = $match[1] ?? null;
                $skillName = trim($match[2] ?? '');
                $conditionType = $match[3] ?? null;
                $conditionText = isset($match[4]) ? trim($match[4]) : null;

                // Build full condition string
                $condition = null;
                if ($conditionType && $conditionText) {
                    $condition = strtolower($conditionType).' '.trim($conditionText);
                }

                $modifiers[] = $this->buildExpertiseModifier(
                    $skillName,
                    null,
                    $abilityScore,
                    $grantsProficiency,
                    $condition
                );
            }
        }

        // Pattern 2: "add double/twice your proficiency bonus to Ability (Skill) checks"
        // For cases where the skill comes AFTER "add double"
        $addDoubleToPattern = '/add\s+(?:double|twice)\s+your\s+proficiency\s+bonus\s+to\s+([A-Z][a-z]+)\s*\(([^)]+)\)\s+checks?/i';

        if (preg_match_all($addDoubleToPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $abilityScore = $match[1] ?? null;
                $skillName = trim($match[2] ?? '');

                if (! $this->expertiseModifierExists($modifiers, $skillName)) {
                    $modifiers[] = $this->buildExpertiseModifier(
                        $skillName,
                        null,
                        $abilityScore,
                        $grantsProficiency,
                        null
                    );
                }
            }
        }

        // Pattern 3: "proficient in the X skill and add double your proficiency bonus"
        $proficientAndDoublePattern = '/proficient\s+in\s+(?:the\s+)?([A-Z][a-z]+)\s+skill[^.]*add\s+(?:double|twice)\s+your\s+proficiency\s+bonus/i';

        if (preg_match_all($proficientAndDoublePattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $skillName = trim($match[1]);

                if (! $this->expertiseModifierExists($modifiers, $skillName)) {
                    $modifiers[] = $this->buildExpertiseModifier(
                        $skillName,
                        null,
                        null,
                        true,
                        null
                    );
                }
            }
        }

        // Pattern 4: "proficiency bonus is doubled for any ability check ... uses the X skill"
        $doubledForSkillPattern = '/proficiency\s+bonus\s+is\s+doubled\s+for\s+any\s+ability\s+check[^.]*(?:uses|using)\s+(?:the\s+)?([A-Z][a-z]+)\s+skill/i';

        if (preg_match_all($doubledForSkillPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $skillName = trim($match[1]);

                if (! $this->expertiseModifierExists($modifiers, $skillName)) {
                    $modifiers[] = $this->buildExpertiseModifier(
                        $skillName,
                        null,
                        null,
                        false,
                        null
                    );
                }
            }
        }

        // Pattern 5: "have expertise in the X skill"
        $expertiseInPattern = '/(?:have|gain)\s+expertise\s+in\s+(?:the\s+)?([A-Z][a-z]+)\s+skill/i';

        if (preg_match_all($expertiseInPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $skillName = trim($match[1]);

                if (! $this->expertiseModifierExists($modifiers, $skillName)) {
                    $modifiers[] = $this->buildExpertiseModifier(
                        $skillName,
                        null,
                        null,
                        false,
                        null
                    );
                }
            }
        }

        // Pattern 6: "ability check with X tools ... add double your proficiency bonus"
        $toolPattern = '/ability\s+check\s+with\s+([^,]+?(?:tools?|kit))[^.]*(?:add\s+(?:double|twice)\s+your\s+proficiency\s+bonus|proficiency\s+bonus\s+is\s+doubled)/i';

        if (preg_match_all($toolPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $toolName = trim($match[1]);

                $modifiers[] = $this->buildExpertiseModifier(
                    null,
                    $toolName,
                    null,
                    false,
                    null
                );
            }
        }

        return $modifiers;
    }
}
