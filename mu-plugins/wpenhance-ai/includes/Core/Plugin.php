<?php

namespace WPEnhance\AI\Core;

use WPEnhance\AI\Admin\AdminToolbar;
use WPEnhance\AI\Admin\MetaBox;
use WPEnhance\AI\Admin\SettingsPage;
use WPEnhance\AI\Features\Registry;
use WPEnhance\AI\REST\FeatureController;

defined('ABSPATH') || exit;

class Plugin {

    public static function init(): void {

        add_action('init', [self::class, 'boot']);
    }

    public static function boot(): void {

        Registry::init();

        MetaBox::init();
        SettingsPage::init();
        AdminToolbar::init();

        FeatureController::init();
    }
}
