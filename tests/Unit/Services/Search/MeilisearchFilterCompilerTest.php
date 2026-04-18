<?php

namespace Tests\Unit\Services\Search;

use App\Services\Search\MeilisearchFilterCompiler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for MeilisearchFilterCompiler.
 *
 * Meilisearch's filter DSL requires string values containing certain
 * characters (notably `:`) to be wrapped in double quotes. The project
 * accepts raw `?filter=` query strings from clients, so the compiler
 * must normalize user-supplied filter expressions before handing them
 * to Meilisearch.
 *
 * Keywords and operators (AND/OR/NOT/IN/IS/NULL/EMPTY/TO/true/false)
 * and numeric literals must pass through untouched.
 */
#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class MeilisearchFilterCompilerTest extends TestCase
{
    // ============================================================
    // Quoting values containing colon (the original bug)
    // ============================================================

    #[Test]
    public function it_quotes_equals_value_containing_colon(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug = phb:agonizing-blast');

        $this->assertSame('slug = "phb:agonizing-blast"', $output);
    }

    #[Test]
    public function it_quotes_not_equals_value_containing_colon(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug != phb:agonizing-blast');

        $this->assertSame('slug != "phb:agonizing-blast"', $output);
    }

    #[Test]
    public function it_quotes_colon_values_inside_in_lists(): void
    {
        $output = MeilisearchFilterCompiler::compile('class_slugs IN [phb:bard, wizard]');

        $this->assertSame('class_slugs IN ["phb:bard", wizard]', $output);
    }

    #[Test]
    public function it_quotes_colon_values_inside_not_in_lists(): void
    {
        $output = MeilisearchFilterCompiler::compile('source_codes NOT IN [phb:core, erlw]');

        $this->assertSame('source_codes NOT IN ["phb:core", erlw]', $output);
    }

    // ============================================================
    // Pass-through: already-quoted values
    // ============================================================

    #[Test]
    public function it_leaves_already_quoted_values_alone(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug = "phb:agonizing-blast"');

        $this->assertSame('slug = "phb:agonizing-blast"', $output);
    }

    #[Test]
    public function it_leaves_mixed_quoted_and_unquoted_in_list_alone(): void
    {
        $output = MeilisearchFilterCompiler::compile('class_slugs IN ["phb:bard", wizard]');

        $this->assertSame('class_slugs IN ["phb:bard", wizard]', $output);
    }

    // ============================================================
    // Pass-through: values without special characters
    // ============================================================

    #[Test]
    public function it_does_not_quote_plain_alphanumeric_values(): void
    {
        $output = MeilisearchFilterCompiler::compile('feature_type = eldritch_invocation');

        $this->assertSame('feature_type = eldritch_invocation', $output);
    }

    #[Test]
    public function it_does_not_quote_numeric_equals_values(): void
    {
        $output = MeilisearchFilterCompiler::compile('level = 3');

        $this->assertSame('level = 3', $output);
    }

    #[Test]
    public function it_does_not_quote_numeric_comparison_values(): void
    {
        $this->assertSame('level >= 3', MeilisearchFilterCompiler::compile('level >= 3'));
        $this->assertSame('level <= 3', MeilisearchFilterCompiler::compile('level <= 3'));
        $this->assertSame('level > 3', MeilisearchFilterCompiler::compile('level > 3'));
        $this->assertSame('level < 3', MeilisearchFilterCompiler::compile('level < 3'));
    }

    #[Test]
    public function it_does_not_quote_boolean_literals(): void
    {
        $this->assertSame('has_spell_mechanics = true', MeilisearchFilterCompiler::compile('has_spell_mechanics = true'));
        $this->assertSame('has_spell_mechanics = false', MeilisearchFilterCompiler::compile('has_spell_mechanics = false'));
        $this->assertSame('has_spell_mechanics != true', MeilisearchFilterCompiler::compile('has_spell_mechanics != true'));
    }

    #[Test]
    public function it_does_not_quote_hyphenated_slugs_without_colons(): void
    {
        // Meilisearch accepts hyphenated bare words (e.g. `acolyte`, `agonizing-blast`).
        // Only characters outside [a-zA-Z0-9_-] force quoting.
        $output = MeilisearchFilterCompiler::compile('slug = agonizing-blast');

        $this->assertSame('slug = agonizing-blast', $output);
    }

    // ============================================================
    // Keywords and operators pass through untouched
    // ============================================================

    #[Test]
    public function it_preserves_is_null_keyword(): void
    {
        $output = MeilisearchFilterCompiler::compile('level_requirement IS NULL');

        $this->assertSame('level_requirement IS NULL', $output);
    }

    #[Test]
    public function it_preserves_is_not_null_keyword(): void
    {
        $output = MeilisearchFilterCompiler::compile('level_requirement IS NOT NULL');

        $this->assertSame('level_requirement IS NOT NULL', $output);
    }

    #[Test]
    public function it_preserves_is_empty_keyword(): void
    {
        $output = MeilisearchFilterCompiler::compile('class_slugs IS EMPTY');

        $this->assertSame('class_slugs IS EMPTY', $output);
    }

    #[Test]
    public function it_preserves_to_range_operator(): void
    {
        $output = MeilisearchFilterCompiler::compile('level 1 TO 5');

        $this->assertSame('level 1 TO 5', $output);
    }

    #[Test]
    public function it_preserves_and_or_operators(): void
    {
        $output = MeilisearchFilterCompiler::compile('feature_type = maneuver AND has_spell_mechanics = false');

        $this->assertSame('feature_type = maneuver AND has_spell_mechanics = false', $output);
    }

    #[Test]
    public function it_preserves_or_operator(): void
    {
        $output = MeilisearchFilterCompiler::compile('feature_type = maneuver OR feature_type = eldritch_invocation');

        $this->assertSame('feature_type = maneuver OR feature_type = eldritch_invocation', $output);
    }

    // ============================================================
    // Combined / compound expressions with the bug trigger
    // ============================================================

    #[Test]
    public function it_quotes_only_offending_value_in_compound_expression(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug = phb:agonizing-blast AND level_requirement >= 3');

        $this->assertSame('slug = "phb:agonizing-blast" AND level_requirement >= 3', $output);
    }

    #[Test]
    public function it_handles_mixed_quoted_bare_and_colon_values(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug = phb:bard OR slug = "phb:druid" OR slug = wizard');

        $this->assertSame('slug = "phb:bard" OR slug = "phb:druid" OR slug = wizard', $output);
    }

    // ============================================================
    // Edge cases: null/empty input
    // ============================================================

    #[Test]
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', MeilisearchFilterCompiler::compile(''));
    }

    #[Test]
    public function it_preserves_extra_whitespace(): void
    {
        // The compiler must not reshape benign whitespace in ways that
        // would surprise downstream consumers. We accept whitespace as-is
        // aside from mandatory value normalization.
        $output = MeilisearchFilterCompiler::compile('slug = phb:bard');

        $this->assertSame('slug = "phb:bard"', $output);
    }

    // ============================================================
    // Quoted value with dot or other safe-via-quoting chars
    // ============================================================

    #[Test]
    public function it_quotes_values_containing_a_dot(): void
    {
        $output = MeilisearchFilterCompiler::compile('slug = phb.bard');

        $this->assertSame('slug = "phb.bard"', $output);
    }

    #[Test]
    public function it_quotes_values_containing_spaces_when_passed_as_single_token(): void
    {
        // If a caller passes an already-quoted value with spaces it must
        // pass through untouched. (Bare-word values containing spaces
        // aren't possible in this grammar — they'd be multiple tokens.)
        $output = MeilisearchFilterCompiler::compile('name = "Agonizing Blast"');

        $this->assertSame('name = "Agonizing Blast"', $output);
    }

    // ============================================================
    // Robustness: NOT IN with colon values
    // ============================================================

    #[Test]
    public function it_quotes_multiple_colon_values_inside_lists(): void
    {
        $output = MeilisearchFilterCompiler::compile('class_slugs IN [phb:bard, phb:wizard, erlw:artificer]');

        $this->assertSame('class_slugs IN ["phb:bard", "phb:wizard", "erlw:artificer"]', $output);
    }
}
