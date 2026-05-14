<?php

namespace WPEnhance\AI\Core;

defined('ABSPATH') || exit;

class Config {

    private const OPT_PROVIDER = 'wpenhance_ai_provider';

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
}
