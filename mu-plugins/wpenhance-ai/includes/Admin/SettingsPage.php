<?php

namespace WPEnhance\AI\Admin;

use WPEnhance\AI\Core\KeyStore;

defined('ABSPATH') || exit;

/**
 * Settings → WPEnhance AI
 *
 * Provides a standard WordPress settings page where administrators can:
 *   - Choose the active AI provider (Anthropic / OpenAI)
 *   - Enter and store API keys (AES-256 encrypted via KeyStore)
 *   - See where each key is currently sourced from
 *   - Remove a stored database key
 *
 * Keys entered here are encrypted before being saved to wp_options.
 * If a key is already configured via an env var or a wp-config.php constant,
 * that source takes lower priority than the database — but it is shown to
 * the administrator so they know a fallback is active.
 */
class SettingsPage {

    private const PAGE_SLUG    = 'wpenhance-ai';
    private const NONCE_ACTION = 'wpenhance_ai_save_settings';
    private const NONCE_FIELD  = 'wpenhance_ai_nonce';
    private const OPT_PROVIDER = 'wpenhance_ai_provider';

    /** @var array<string, string> Provider slugs → human labels. */
    private const PROVIDERS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai'    => 'OpenAI (GPT)',
        'gemini'    => 'Google (Gemini)',
    ];

    // ── Initialisation ────────────────────────────────────────────────────────

    public static function init(): void {

        add_action('admin_menu',                   [self::class, 'register_menu']);
        add_action('admin_post_' . self::PAGE_SLUG, [self::class, 'handle_save']);
    }

    public static function register_menu(): void {

        add_options_page(
            'WPEnhance AI',
            'WPEnhance AI',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    // ── Form handler ──────────────────────────────────────────────────────────

    public static function handle_save(): void {

        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to manage these settings.'),
                403
            );
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        // ── Provider ──────────────────────────────────────────────────────────
        $provider = sanitize_key($_POST[self::OPT_PROVIDER] ?? '');

        if (array_key_exists($provider, self::PROVIDERS)) {
            update_option(self::OPT_PROVIDER, $provider, false);
        }

        // ── API keys — save if non-empty, remove if checkbox checked ──────────
        foreach (array_keys(self::PROVIDERS) as $slug) {

            // Explicit removal takes precedence over a new value.
            if (!empty($_POST["wpenhance_ai_remove_{$slug}"])) {
                KeyStore::delete($slug);
                continue;
            }

            $new_key = sanitize_text_field(
                trim($_POST["wpenhance_ai_key_{$slug}"] ?? '')
            );

            if ($new_key !== '') {
                KeyStore::set($slug, $new_key);
            }
            // If the field was left blank, the existing key is preserved.
        }

        wp_safe_redirect(
            add_query_arg(
                'wpenhance_saved',
                '1',
                admin_url('options-general.php?page=' . self::PAGE_SLUG)
            )
        );
        exit;
    }

    // ── Page renderer ─────────────────────────────────────────────────────────

    public static function render(): void {

        if (!current_user_can('manage_options')) {
            return;
        }

        $saved_provider = (string) get_option(self::OPT_PROVIDER, '');
        $active_provider = $saved_provider !== ''
            ? $saved_provider
            : (defined('WPENHANCE_AI_PROVIDER') ? WPENHANCE_AI_PROVIDER : 'anthropic');

        ?>
        <div class="wrap">

            <h1><?php esc_html_e('WPEnhance AI — Settings'); ?></h1>

            <?php if (!empty($_GET['wpenhance_saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.'); ?></p>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            >
                <input
                    type="hidden"
                    name="action"
                    value="<?php echo esc_attr(self::PAGE_SLUG); ?>"
                >

                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <!-- ── Provider ──────────────────────────────────────────── -->
                <h2><?php esc_html_e('Active Provider'); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wpenhance_ai_provider">
                                <?php esc_html_e('Provider'); ?>
                            </label>
                        </th>
                        <td>
                            <select
                                name="<?php echo esc_attr(self::OPT_PROVIDER); ?>"
                                id="wpenhance_ai_provider"
                            >
                                <?php foreach (self::PROVIDERS as $slug => $label): ?>
                                    <option
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php selected($active_provider, $slug); ?>
                                    >
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($saved_provider === '' && defined('WPENHANCE_AI_PROVIDER')): ?>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: constant name */
                                        esc_html__(
                                            'Currently inherited from the %s constant. ' .
                                            'Selecting a value here will override it.'
                                        ),
                                        '<code>WPENHANCE_AI_PROVIDER</code>'
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- ── API Keys ──────────────────────────────────────────── -->
                <h2><?php esc_html_e('API Keys'); ?></h2>

                <p>
                    <?php
                    esc_html_e(
                        'Keys are encrypted with AES-256-CBC before being stored ' .
                        'in the WordPress database. The encryption secret is ' .
                        'derived from your WordPress auth salts (wp-config.php), ' .
                        'so plaintext keys never touch the database.'
                    );
                    ?>
                </p>

                <table class="form-table" role="presentation">

                    <?php foreach (self::PROVIDERS as $slug => $label): ?>

                        <?php
                        $source     = KeyStore::source($slug);
                        $configured = $source !== null;
                        ?>

                        <tr>
                            <th scope="row">
                                <label for="wpenhance_ai_key_<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($label); ?>
                                    <?php esc_html_e('API Key'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="password"
                                    id="wpenhance_ai_key_<?php echo esc_attr($slug); ?>"
                                    name="wpenhance_ai_key_<?php echo esc_attr($slug); ?>"
                                    class="regular-text"
                                    autocomplete="new-password"
                                    placeholder="<?php
                                        echo $configured
                                            ? esc_attr('••••••••••••••••')
                                            : esc_attr('Paste your API key…');
                                    ?>"
                                >

                                <span class="wpenhance-ai-key-badge <?php
                                    echo $configured ? 'wpenhance-ai-badge--ok' : 'wpenhance-ai-badge--missing';
                                ?>">
                                    <?php if ($configured): ?>
                                        <?php echo esc_html('✓ Configured'); ?>
                                        <span class="wpenhance-ai-key-source">
                                            (<?php echo esc_html($source); ?>)
                                        </span>
                                    <?php else: ?>
                                        <?php esc_html_e('✗ Not configured'); ?>
                                    <?php endif; ?>
                                </span>

                                <p class="description">
                                    <?php
                                    esc_html_e(
                                        'Leave blank to keep the existing key. ' .
                                        'Enter a new value to replace it.'
                                    );
                                    ?>
                                </p>

                                <?php if ($source === 'database'): ?>
                                    <p>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="wpenhance_ai_remove_<?php echo esc_attr($slug); ?>"
                                                value="1"
                                            >
                                            <?php esc_html_e('Remove stored key'); ?>
                                        </label>
                                    </p>
                                <?php elseif ($source === 'environment' || $source === 'constant'): ?>
                                    <p class="description">
                                        <?php
                                        printf(
                                            esc_html__(
                                                'This key is currently supplied by a server %s and ' .
                                                'cannot be removed here. Enter a new key above to ' .
                                                'override it with a database value.'
                                            ),
                                            $source === 'environment'
                                                ? esc_html__('environment variable')
                                                : esc_html__('PHP constant')
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                </table>

                <!-- ── Security note ─────────────────────────────────────── -->
                <div class="wpenhance-ai-settings-note">
                    <p>
                        <strong><?php esc_html_e('Alternative (server-side):'); ?></strong>
                        <?php
                        esc_html_e(
                            'You can also define keys as constants or environment ' .
                            'variables (e.g. in wp-config.php). Those sources are ' .
                            'used automatically as a fallback when no database key ' .
                            'is stored.'
                        );
                        ?>
                    </p>
                    <pre class="wpenhance-ai-code-sample">define( 'ANTHROPIC_API_KEY', 'sk-ant-…' );
define( 'OPENAI_API_KEY',    'sk-…' );</pre>
                    <p>
                        <?php
                        esc_html_e(
                            'To use a custom encryption secret (instead of the ' .
                            'derived wp_salt value), add this to wp-config.php:'
                        );
                        ?>
                    </p>
                    <pre class="wpenhance-ai-code-sample">define( 'WPENHANCE_AI_SECRET', 'your-random-secret' );</pre>
                </div>

                <?php submit_button('Save Settings'); ?>

            </form>

        </div>

        <style>
            .wpenhance-ai-key-badge {
                display: inline-block;
                margin-left: 8px;
                font-size: 12px;
                font-weight: 600;
                vertical-align: middle;
            }
            .wpenhance-ai-badge--ok      { color: #46b450; }
            .wpenhance-ai-badge--missing { color: #dc3232; }
            .wpenhance-ai-key-source {
                font-weight: 400;
                color: #646970;
            }
            .wpenhance-ai-settings-note {
                background: #f6f7f7;
                border-left: 4px solid #c3c4c7;
                padding: 12px 16px;
                margin: 20px 0;
                max-width: 600px;
            }
            .wpenhance-ai-settings-note p {
                margin: 6px 0;
            }
            .wpenhance-ai-code-sample {
                background: #fff;
                border: 1px solid #dcdcde;
                padding: 8px 12px;
                font-size: 12px;
                margin: 6px 0 10px;
                overflow-x: auto;
            }
        </style>
        <?php
    }
}
