<?php

namespace WPEnhance\AI\Core;

defined('ABSPATH') || exit;

/**
 * Extracts translatable text from WordPress block comment attributes,
 * replacing each value with a __WPAI_N__ placeholder before translation
 * and reinserting the translated strings afterwards.
 *
 * This solves the silent-skip problem for blocks like wp:details whose
 * visible text (e.g. the accordion summary) lives inside the block's JSON
 * attribute object rather than in the HTML body.  A prompt that only
 * instructs the model to "translate visible text between HTML tags" never
 * sees those strings — they stay in the original language even after the
 * HTML content is fully translated, and the block editor re-renders them
 * from the untouched JSON when the post is opened.
 *
 * Flow:
 *   1. extract()  — parse_blocks() → replace attr values → serialize_blocks()
 *   2. Translation API call (placeholders survive unchanged in block JSON)
 *   3. Separate ===ATTRS=== prompt section returns translated attr strings
 *   4. reinsert() — swap placeholders for JSON-safe translated values
 */
class BlockTextExtractor {

    /**
     * Attribute names known to carry user-visible, translatable text.
     * Checked at every depth of the block tree.
     *
     * @var string[]
     */
    private const TRANSLATABLE_ATTRS = [
        'summary',      // wp:details — accordion / disclosure summary
        'alt',          // wp:image, wp:media-text — image alternative text
        'caption',      // wp:image — caption stored in block attrs
        'label',        // wp:search, wp:navigation-link — input or nav label
        'placeholder',  // wp:search — input placeholder text
        'buttonText',   // wp:search, wp:file — button label
        'title',        // wp:rss and various plugin blocks — display title
        'description',  // various plugin blocks — visible description text
    ];

    /**
     * Walk the parsed block tree, replace every translatable attribute value
     * with a __WPAI_N__ placeholder, and return the re-serialised content
     * together with the extraction map.
     *
     * When no translatable attributes are found the original content is
     * returned unchanged and the map is empty, so callers can skip the
     * attrs translation pass entirely.
     *
     * @param  string $content  Raw WordPress post_content string.
     * @return array{0: string, 1: array<string, string>}
     *   [0] Content with placeholders substituted in block comment JSON.
     *   [1] Map of placeholder → original string (empty if nothing found).
     */
    public static function extract(string $content): array {

        // parse_blocks / serialize_blocks require WordPress 5.0+.
        // Our minimum is 6.3, but guard defensively anyway.
        if (!function_exists('parse_blocks')) {
            return [$content, []];
        }

        $map    = [];
        $index  = 0;
        $blocks = parse_blocks($content);

        self::walk($blocks, $map, $index);

        if (empty($map)) {
            return [$content, []];
        }

        return [serialize_blocks($blocks), $map];
    }

    /**
     * Replace __WPAI_N__ placeholders in content with their translated values.
     *
     * Values are JSON-escaped before substitution because the placeholders sit
     * inside JSON string fields within block comment attributes.  Inserting an
     * unescaped double-quote or backslash would silently corrupt the block
     * grammar, breaking the editor's ability to parse the post.
     *
     * @param  string               $content      Content containing placeholders.
     * @param  array<string,string> $translations Placeholder → translated string.
     * @return string
     */
    public static function reinsert(string $content, array $translations): string {

        if (empty($translations)) {
            return $content;
        }

        foreach ($translations as $placeholder => $translated) {

            // json_encode produces `"escaped string"` — strip the outer quotes
            // to get a bare JSON-safe value ready for inline substitution.
            $json_escaped = substr(
                (string) json_encode((string) $translated, JSON_UNESCAPED_UNICODE),
                1,
                -1
            );

            $content = str_replace((string) $placeholder, $json_escaped, $content);
        }

        return $content;
    }

    // ── Block structure repair ────────────────────────────────────────────────

    // ── Block structure repair ────────────────────────────────────────────────

    /**
     * Detect and repair broken block nesting in the translated content by
     * comparing it against the original's block tree.
     *
     * Two failure modes are handled, applied in order:
     *
     * CASE 1 — Duplication (translated has MORE top-level blocks than original)
     *   The model emits inner blocks (e.g. accordion items) as top-level blocks
     *   BEFORE the container, then also outputs the full container with those
     *   same inner blocks correctly nested inside it.  Result: the items appear
     *   twice — as rogue top-level blocks and again inside the container.
     *
     *   Fix: walk the translated top-level block list and greedily match each
     *   block against the next expected block name from the original.  Blocks
     *   that do not match are skipped — they are the duplicates.  The correctly-
     *   nested copies inside the container are preserved by parse_blocks() and
     *   remain in the kept container's innerBlocks untouched.
     *
     * CASE 2 — Escape without duplication (translated has FEWER top-level blocks)
     *   The model places the container's closing HTML tag too early, leaving the
     *   last item(s) as top-level blocks AFTER the container without a copy
     *   inside it.
     *
     *   Fix: absorb the following siblings back into the container's innerBlocks
     *   and add the required null slots to innerContent so serialize_blocks()
     *   places them at the right position inside the container's HTML.
     *
     * Both repairs include a safe bail-out: if the algorithm cannot fully
     * reconcile the block count, the original translated string is returned
     * unchanged rather than producing something potentially worse.
     *
     * @param  string $translated  Translated post content (may have broken nesting).
     * @param  string $original    Original post content (authoritative structure).
     * @return string              Content with block structure repaired where possible.
     */
    public static function repair_structure(string $translated, string $original): string {

        if (
            !function_exists('parse_blocks') ||
            !function_exists('serialize_blocks')
        ) {
            return $translated;
        }

        // Work with named (non-freeform) top-level blocks only; the freeform
        // null-blockName entries are inter-block whitespace that serialize_blocks()
        // recreates automatically.
        $orig_top  = self::named_blocks(parse_blocks($original));
        $trans_top = self::named_blocks(parse_blocks($translated));

        if (count($orig_top) === count($trans_top)) {
            return $translated; // top-level structure matches — nothing to repair
        }

        // ── CASE 1: more blocks than expected — remove top-level duplicates ───
        if (count($trans_top) > count($orig_top)) {

            $filtered = self::remove_top_level_duplicates($orig_top, $trans_top);

            if (count($filtered) === count($orig_top)) {
                return serialize_blocks($filtered);
            }

            // If the filter didn't reach the expected count, fall through to
            // the bail-out below rather than emitting a partially-repaired result.
        }

        // ── CASE 2: fewer blocks than expected — absorb escaped siblings ──────
        if (count($trans_top) < count($orig_top)) {

            $repaired = self::reattach_escaped_siblings($orig_top, $trans_top);

            if (count($repaired) === count($orig_top)) {
                return serialize_blocks($repaired);
            }
        }

        return $translated; // could not repair — return content unchanged
    }

    /**
     * CASE 1 repair: walk the translated top-level block list and keep only
     * the blocks whose blockName matches the next expected block from the
     * original sequence.  Blocks that do not match are skipped — they are
     * inner blocks that the model erroneously duplicated at the top level.
     *
     * Example:
     *   original:   [accordion]
     *   translated: [accordion-panel, accordion-item, accordion]  ← two extra
     *   result:     [accordion]                                    ← duplicates removed
     *
     * The accordion block's innerBlocks (which already contain the correctly-
     * nested panel and item as parsed by parse_blocks()) are preserved intact.
     *
     * @param  array $original   Named top-level blocks from the original parse.
     * @param  array $translated Named top-level blocks from the translated parse.
     * @return array             Filtered block list with duplicates removed.
     */
    private static function remove_top_level_duplicates(array $original, array $translated): array {

        $kept   = [];
        $orig_i = 0;

        foreach ($translated as $trans_block) {

            if ($orig_i >= count($original)) {
                break;
            }

            if ($trans_block['blockName'] === $original[$orig_i]['blockName']) {
                $kept[] = $trans_block;
                $orig_i++;
            }
            // else: block name doesn't match the next expected — skip it (duplicate)
        }

        return $kept;
    }

    /**
     * CASE 2 repair: walk original and translated block arrays in parallel.
     * When a translated container has fewer innerBlocks than expected, look at
     * the immediately following translated siblings and absorb those whose
     * blockName matches the expected missing inner block type.
     *
     * Also updates innerContent with the required null slots so serialize_blocks()
     * places the re-nested blocks inside the container's HTML at the correct
     * position.
     *
     * @param  array $original   Named blocks from the original parse.
     * @param  array $translated Named blocks from the translated parse.
     * @return array             Block array with escaped siblings re-nested.
     */
    private static function reattach_escaped_siblings(array $original, array $translated): array {

        $result = [];
        $t_idx  = 0;

        foreach ($original as $orig_block) {

            if ($t_idx >= count($translated)) {
                break;
            }

            $trans_block = $translated[$t_idx];
            $t_idx++;

            // Block names must pair up for the parallel walk to be reliable.
            if ($trans_block['blockName'] !== $orig_block['blockName']) {
                return $translated; // unexpected mismatch — bail out safely
            }

            $orig_inner_count  = count($orig_block['innerBlocks']  ?? []);
            $trans_inner_count = count($trans_block['innerBlocks'] ?? []);

            if ($orig_inner_count > $trans_inner_count) {

                $needed   = $orig_inner_count - $trans_inner_count;
                $absorbed = [];

                for ($i = 0; $i < $needed && $t_idx < count($translated); $i++) {

                    $sibling       = $translated[$t_idx];
                    $expected_name = $orig_block['innerBlocks'][$trans_inner_count + $i]['blockName'] ?? null;

                    if ($sibling['blockName'] !== $expected_name) {
                        break;
                    }

                    $absorbed[] = $sibling;
                    $t_idx++;
                }

                if (!empty($absorbed)) {

                    $trans_block['innerBlocks'] = array_merge(
                        $trans_block['innerBlocks'] ?? [],
                        $absorbed
                    );

                    // Insert null slots before the last closing HTML string in
                    // innerContent so serialize_blocks() places the newly
                    // absorbed inner blocks inside the container's HTML wrapper.
                    $ic       = $trans_block['innerContent'] ?? [];
                    $last_key = null;

                    foreach (array_reverse(array_keys($ic), true) as $k) {
                        if (is_string($ic[$k])) {
                            $last_key = $k;
                            break;
                        }
                    }

                    if ($last_key !== null) {
                        array_splice(
                            $ic,
                            $last_key,
                            0,
                            array_fill(0, count($absorbed), null)
                        );
                        $trans_block['innerContent'] = $ic;
                    }
                }
            }

            // Recurse into inner blocks.
            if ($orig_inner_count > 0 && !empty($trans_block['innerBlocks'])) {
                $trans_block['innerBlocks'] = self::reattach_escaped_siblings(
                    $orig_block['innerBlocks'],
                    $trans_block['innerBlocks']
                );
            }

            $result[] = $trans_block;
        }

        return $result;
    }

    /**
     * Return only named blocks (those with a non-null blockName), discarding
     * the freeform whitespace fragments that parse_blocks() inserts between
     * block comments.
     *
     * @param  array $blocks  Raw parse_blocks() output.
     * @return array          Re-indexed array of named blocks.
     */
    private static function named_blocks(array $blocks): array {

        return array_values(
            array_filter($blocks, static fn($b) => $b['blockName'] !== null)
        );
    }

    // ── Block attribute walk ──────────────────────────────────────────────────

    /**
     * Recursively walk a parsed block array, replacing translatable attribute
     * string values with __WPAI_N__ tokens.
     *
     * @param array[] $blocks  Parsed block array, passed by reference.
     * @param array   $map     Extraction map, passed by reference.
     * @param int     $index   Running placeholder index, passed by reference.
     */
    private static function walk(array &$blocks, array &$map, int &$index): void {

        foreach ($blocks as &$block) {

            foreach (self::TRANSLATABLE_ATTRS as $attr) {

                if (
                    isset($block['attrs'][$attr])
                    && is_string($block['attrs'][$attr])
                    && trim($block['attrs'][$attr]) !== ''
                ) {
                    $placeholder           = '__WPAI_' . $index . '__';
                    $map[$placeholder]     = $block['attrs'][$attr];
                    $block['attrs'][$attr] = $placeholder;
                    $index++;
                }
            }

            if (!empty($block['innerBlocks'])) {
                self::walk($block['innerBlocks'], $map, $index);
            }
        }
    }
}
