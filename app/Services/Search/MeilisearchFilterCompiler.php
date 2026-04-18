<?php

namespace App\Services\Search;

/**
 * Compiles user-supplied `?filter=` expressions into Meilisearch-safe
 * filter syntax.
 *
 * Meilisearch's filter DSL requires string literals containing characters
 * outside the bare-word alphabet (e.g. `:`, `.`, whitespace) to be wrapped
 * in double quotes. Clients of this API are allowed to send natural-looking
 * filter expressions (e.g. `slug = phb:agonizing-blast`), so we normalize
 * them here before handing the expression to Meilisearch.
 *
 * Rules:
 *  - Operators (`=`, `!=`, `>`, `<`, `>=`, `<=`, `TO`) introduce a value.
 *  - `IN` and `NOT IN` are followed by a `[...]` list whose elements are values.
 *  - Keywords (`AND`, `OR`, `NOT`, `IN`, `IS`, `NULL`, `EMPTY`, `TO`,
 *    `true`, `false`) are never quoted.
 *  - Numeric literals are never quoted.
 *  - Values already enclosed in double quotes pass through unchanged.
 *  - Bare-word values matching `[A-Za-z_][A-Za-z0-9_-]*` pass through unquoted.
 *  - Any other bare-word value is wrapped in double quotes. Embedded `"`
 *    characters are backslash-escaped.
 *
 * This class is a pure stateless utility. It is safe to call statically and
 * trivially unit-testable without infrastructure dependencies.
 */
final class MeilisearchFilterCompiler
{
    /**
     * Operators that introduce a value in the next token position.
     */
    private const BINARY_VALUE_OPERATORS = ['=', '!=', '>=', '<=', '>', '<'];

    /**
     * Keywords that must never be treated as values (never quoted).
     *
     * Matched case-insensitively.
     */
    private const RESERVED_KEYWORDS = [
        'AND', 'OR', 'NOT', 'IN', 'IS', 'NULL', 'EMPTY', 'TO', 'TRUE', 'FALSE',
    ];

    /**
     * Pattern for a bare word that Meilisearch accepts without quoting.
     */
    private const BARE_WORD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_-]*\z/';

    /**
     * Pattern for a numeric literal (integer or decimal, optionally signed).
     */
    private const NUMERIC_PATTERN = '/\A-?\d+(?:\.\d+)?\z/';

    /**
     * Compile a filter expression into Meilisearch-safe syntax.
     *
     * Null/empty input is returned unchanged. The compiler is idempotent:
     * compiling an already-compiled expression produces the same result.
     */
    public static function compile(string $filter): string
    {
        if ($filter === '') {
            return '';
        }

        $tokens = self::tokenize($filter);
        $output = [];
        $expectValue = false;
        $inList = 0; // depth counter for `[ ... ]`

        foreach ($tokens as $token) {
            $type = $token['type'];
            $text = $token['text'];

            if ($type === 'whitespace') {
                $output[] = $text;

                continue;
            }

            if ($type === 'punct') {
                if ($text === '[') {
                    $inList++;
                    $expectValue = false;
                } elseif ($text === ']') {
                    $inList = max(0, $inList - 1);
                    $expectValue = false;
                } elseif ($text === ',') {
                    // Commas delimit values inside lists; the next non-ws
                    // token is another value. Outside of lists, we leave
                    // expectValue untouched.
                    // (List membership determines the position.)
                }
                $output[] = $text;

                continue;
            }

            if ($type === 'quoted') {
                // Already-quoted string literal: pass through untouched.
                $output[] = $text;
                $expectValue = false;

                continue;
            }

            if ($type === 'word') {
                $upper = strtoupper($text);

                // Operators that introduce a value position.
                if (in_array($text, self::BINARY_VALUE_OPERATORS, true)) {
                    $output[] = $text;
                    $expectValue = true;

                    continue;
                }

                // TO range operator also introduces a value position.
                if ($upper === 'TO') {
                    $output[] = $text;
                    $expectValue = true;

                    continue;
                }

                // Reserved keywords never get quoted.
                if (in_array($upper, self::RESERVED_KEYWORDS, true)) {
                    $output[] = $text;
                    // After IS/NOT/IN we may or may not be expecting a value
                    // but reserved keywords themselves are never quoted, and
                    // the value-ness of the next token will be determined
                    // by list context or an explicit operator.
                    $expectValue = false;

                    continue;
                }

                // A bare word in a value position, or anywhere inside a list,
                // may need quoting.
                if ($expectValue || $inList > 0) {
                    $output[] = self::normalizeValue($text);
                    $expectValue = false;

                    continue;
                }

                // Otherwise it's a field identifier (or the left-hand side
                // of a range: `level 1 TO 5` — the `1` would then be a
                // number, handled below via normalizeValue path). Emit as-is.
                $output[] = $text;
            }
        }

        return implode('', $output);
    }

    /**
     * Tokenize the filter string into a stream of tokens: words,
     * quoted strings, whitespace runs, and punctuation.
     *
     * @return array<int, array{type: string, text: string}>
     */
    private static function tokenize(string $filter): array
    {
        $tokens = [];
        $length = strlen($filter);
        $i = 0;

        while ($i < $length) {
            $ch = $filter[$i];

            // Whitespace run
            if (ctype_space($ch)) {
                $start = $i;
                while ($i < $length && ctype_space($filter[$i])) {
                    $i++;
                }
                $tokens[] = ['type' => 'whitespace', 'text' => substr($filter, $start, $i - $start)];

                continue;
            }

            // Punctuation: [ ] , ( )
            if ($ch === '[' || $ch === ']' || $ch === ',' || $ch === '(' || $ch === ')') {
                $tokens[] = ['type' => 'punct', 'text' => $ch];
                $i++;

                continue;
            }

            // Double-quoted string literal (with backslash escapes).
            if ($ch === '"') {
                $start = $i;
                $i++; // skip opening quote
                while ($i < $length) {
                    $c = $filter[$i];
                    if ($c === '\\' && $i + 1 < $length) {
                        $i += 2;

                        continue;
                    }
                    if ($c === '"') {
                        $i++; // consume closing quote
                        break;
                    }
                    $i++;
                }
                $tokens[] = ['type' => 'quoted', 'text' => substr($filter, $start, $i - $start)];

                continue;
            }

            // Multi-character operators: !=, >=, <=
            if ($i + 1 < $length) {
                $two = substr($filter, $i, 2);
                if ($two === '!=' || $two === '>=' || $two === '<=') {
                    $tokens[] = ['type' => 'word', 'text' => $two];
                    $i += 2;

                    continue;
                }
            }

            // Single-character operators: =, >, <
            if ($ch === '=' || $ch === '>' || $ch === '<') {
                $tokens[] = ['type' => 'word', 'text' => $ch];
                $i++;

                continue;
            }

            // Bare word or value run: everything up to the next whitespace,
            // bracket/comma/paren, quote, or single-/multi-char operator.
            $start = $i;
            while ($i < $length) {
                $c = $filter[$i];
                if (ctype_space($c)) {
                    break;
                }
                if ($c === '[' || $c === ']' || $c === ',' || $c === '(' || $c === ')' || $c === '"') {
                    break;
                }
                if ($c === '=' || $c === '>' || $c === '<') {
                    break;
                }
                // `!` only breaks the run if it's part of `!=`
                if ($c === '!' && $i + 1 < $length && $filter[$i + 1] === '=') {
                    break;
                }
                $i++;
            }
            $text = substr($filter, $start, $i - $start);
            if ($text !== '') {
                $tokens[] = ['type' => 'word', 'text' => $text];
            }
        }

        return $tokens;
    }

    /**
     * Decide whether a bare-word value needs quoting and emit the
     * appropriate Meilisearch-safe literal.
     */
    private static function normalizeValue(string $value): string
    {
        // Numeric literals pass through.
        if (preg_match(self::NUMERIC_PATTERN, $value) === 1) {
            return $value;
        }

        // Boolean and null literals pass through (case-insensitive compare
        // against reserved keywords already handled upstream, but an
        // unquoted `true`/`false`/`null` token could reach here if upstream
        // classification changes).
        $upper = strtoupper($value);
        if ($upper === 'TRUE' || $upper === 'FALSE' || $upper === 'NULL') {
            return $value;
        }

        // Bare words that already match Meilisearch's accepted identifier
        // shape can pass through unquoted.
        if (preg_match(self::BARE_WORD_PATTERN, $value) === 1) {
            return $value;
        }

        // Everything else gets wrapped in double quotes, with any embedded
        // quote characters escaped.
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"'.$escaped.'"';
    }
}
