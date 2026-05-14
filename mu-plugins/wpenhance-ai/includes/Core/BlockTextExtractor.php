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
