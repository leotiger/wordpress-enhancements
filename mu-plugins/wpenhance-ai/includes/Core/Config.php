<?php

namespace WPEnhance\AI\Core;

defined('ABSPATH') || exit;

class Config {

    private const OPT_PROVIDER = 'wpenhance_ai_provider';

    /**
     * Default model strings per provider and tier.
     *
     * Two tiers:
     *   light   — fast / cost-effective model; used by Meta Description and
     *             Excerpt Generator where short structured output is sufficient.
     *   quality — higher-capability model; used by Translation and Content
     *             Generator where accuracy and longer context matter.
     *
     * These are the fallback values when no override has been saved via the
     * Settings page.  To update a model site-wide, go to Settings → WPEnhance AI
     * and enter the new model string in the Models section.
     *
     * @var array<string, array<string, string>>
     */
    private const MODEL_DEFAULTS = [
        'anthropic' => [
            'light'   => 'claude-haiku-4-5-20251001',
            'quality' => 'claude-sonnet-4-6',
        ],
        'openai' => [
            'light'   => 'gpt-4o-mini',
            'quality' => 'gpt-4o',
        ],
        'gemini' => [
            'light'   => 'gemini-2.0-flash',
            'quality' => 'gemini-1.5-pro',
        ],
    ];

    // ── Provider ──────────────────────────────────────────────────────────────

    /**
     * Return the active provider slug.
     *
     * Resolution order:
     *   1. Value stored in wp_options (set via Settings page)
     *   2. WPENHANCE_AI_PROVIDER constant (wp-config.php)
     *   3. Default: 'anthropic'
     */
    public static function provider(): string {

        $stored = (string) get_option(self::OPT_PROVIDER, '');

        if ($stored !== '') {
            return $stored;
        }

        if (defined('WPENHANCE_AI_PROVIDER')) {
            return (string) WPENHANCE_AI_PROVIDER;
        }

        return 'anthropic';
    }

    // ── Model ─────────────────────────────────────────────────────────────────

    /**
     * Return the model string to use for a given tier and the active provider.
     *
     * Resolution order:
     *   1. Value stored in wp_options as wpenhance_ai_model_{provider}_{tier}
     *      (set via Settings → WPEnhance AI → Models)
     *   2. Hard-coded default from MODEL_DEFAULTS above
     *
     * Clearing the stored value (empty string) falls back to the default,
     * so "reset to default" is achieved by leaving the settings field blank.
     *
     * @param string $tier  'light' or 'quality'
     */
    public static function model(string $tier): string {

        $provider   = self::provider();
        $option_key = "wpenhance_ai_model_{$provider}_{$tier}";
        $stored     = (string) get_option($option_key, '');

        if ($stored !== '') {
            return $stored;
        }

        return self::MODEL_DEFAULTS[$provider][$tier]
            ?? self::MODEL_DEFAULTS['anthropic'][$tier]
            ?? self::MODEL_DEFAULTS['anthropic']['light'];
    }

    /**
     * Return the hard-coded default model for a given provider and tier.
     *
     * Used by the Settings page to display placeholder text in model fields.
     *
     * @param string $provider  Provider slug ('anthropic', 'openai', 'gemini').
     * @param string $tier      'light' or 'quality'.
     */
    public static function default_model(string $provider, string $tier): string {

        return self::MODEL_DEFAULTS[$provider][$tier] ?? '';
    }

    /**
     * Expose all provider slugs with their default model tiers.
     *
     * Consumed by the Settings page to render the Models table.
     *
     * @return array<string, array<string, string>>
     */
    public static function all_model_defaults(): array {

        return self::MODEL_DEFAULTS;
    }
}
