<?php
/**
 * Language Routing for WP – Class-based refactor
 * Author: Uli Hake
 * Version: 1.3.2
 *
 * Drop this folder into mu-plugins/ as a replacement for the procedural version.
 * The MY_LANG constant and all wrapper functions are preserved for theme compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================
// AUTOLOAD CLASSES
// =========================================================
require_once __DIR__ . '/includes/class-language-router.php';
require_once __DIR__ . '/includes/class-lsflr-switcher.php';

// =========================================================
// BOOT
// Instantiation defines MY_LANG immediately (same timing as before)
// =========================================================
$language_router  = Language_Router::get_instance();
$lsflr_switcher   = new LSFLR_Switcher( $language_router );

// =========================================================
// THEME / TEMPLATE COMPATIBILITY WRAPPERS
//
// These thin functions delegate to the class instance so
// existing theme code continues to work without changes.
// =========================================================

function my_source_language(): string {
	return Language_Router::get_instance()->source_language();
}

function my_languages(): array {
	return Language_Router::get_instance()->languages();
}

function my_is_valid_lang( $lang ): bool {
	return Language_Router::get_instance()->is_valid_lang( $lang );
}

function my_locale_from_lang( string $lang ): string {
	return Language_Router::get_instance()->locale_from_lang( $lang );
}

function my_language_label( string $lang ): string {
	return Language_Router::get_instance()->language_label( $lang );
}

function my_detect_lang(): string {
	return Language_Router::get_instance()->detect_lang();
}

function my_detect_lang_safe(): string {
	return Language_Router::get_instance()->detect_lang_safe();
}

function my_get_trid( int $id ): string {
	return Language_Router::get_instance()->get_trid( $id );
}

function my_set_trid( int $id, string $v ): void {
	Language_Router::get_instance()->set_trid( $id, $v );
}

function my_get_lang( int $id ): string {
	return Language_Router::get_instance()->get_lang( $id );
}

function my_set_lang( int $id, string $v ): void {
	Language_Router::get_instance()->set_lang( $id, $v );
}

function my_get_translations( int $post_id ): array {
	return Language_Router::get_instance()->get_translations( $post_id );
}

function my_clear_translation_cache( int $post_id ): void {
	Language_Router::get_instance()->clear_translation_cache( $post_id );
}

function my_mark_source_updated( int $post_id ): void {
	Language_Router::get_instance()->mark_source_updated( $post_id );
}

function my_mark_translation_synced( int $post_id ): void {
	Language_Router::get_instance()->mark_translation_synced( $post_id );
}

function my_is_outdated( int $post_id ): bool {
	return Language_Router::get_instance()->is_outdated( $post_id );
}

function my_get_missing_languages( int $post_id ): array {
	return Language_Router::get_instance()->get_missing_languages( $post_id );
}

function my_query( array $args = [] ): WP_Query {
	return Language_Router::get_instance()->query( $args );
}

function my_query_fallback( array $args = [] ): WP_Query {
	return Language_Router::get_instance()->query_fallback( $args );
}

function my_get_posts( array $args = [], bool $fallback = false ): array {
	return Language_Router::get_instance()->get_posts( $args, $fallback );
}

function my_safe_query_args( string $url ): string {
	return Language_Router::get_instance()->safe_query_args( $url );
}

function my_is_system_request(): bool {
	return Language_Router::get_instance()->is_system_request();
}

function my_set_lang_cookie( string $lang ): void {
	Language_Router::get_instance()->set_lang_cookie( $lang );
}

function my_hreflang_mode(): string {
	return Language_Router::get_instance()->hreflang_mode();
}

function my_build_search_content( int $post_id ): void {
	Language_Router::get_instance()->build_search_content( $post_id );
}

function my_ensure_lang_index(): bool {
	return Language_Router::get_instance()->ensure_lang_index();
}

function my_debug( string $message, array $context = [] ): void {
	Language_Router::get_instance()->debug( $message, $context );
}

/**
 * Kept for theme code that calls my_lang_permalink() directly.
 * The filter registration is handled inside the class.
 */
function my_lang_permalink( string $url, $post ): string {
	return Language_Router::get_instance()->lang_permalink( $url, $post );
}

// LSFLR shortcut (for themes calling the render function directly)
function my_lsflr_render_switcher( array $atts = [] ): string {
	global $lsflr_switcher;
	return $lsflr_switcher->render_switcher( $atts );
}

function my_lsflr_get_languages(): array {
	global $lsflr_switcher;
	return $lsflr_switcher->get_languages();
}

function my_lsflr_translate_current_url( string $target_lang, ?int $post_id = null ): string {
	global $lsflr_switcher;
	return $lsflr_switcher->translate_current_url( $target_lang, $post_id );
}
