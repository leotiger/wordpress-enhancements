<?php
/**
 * Plugin Name: WPEnhance AI
 * Description: AI assistance framework for WordPress enhancements.
 * Version: 1.0.5
 */

defined('ABSPATH') || exit;

define('WPENHANCE_AI_PATH', __DIR__);
define('WPENHANCE_AI_URL', content_url('mu-plugins/wpenhance-ai'));

require_once WPENHANCE_AI_PATH . '/includes/Core/Autoloader.php';

\WPEnhance\AI\Core\Plugin::init();