<?php
/**
 * Class LSFLR_Link_Fixer
 *
 * Scans translated posts/pages for internal links that still point to the
 * source-language version of a page and rewrites them to the correct
 * language equivalent using the TRID translation group system.
 *
 * UI: a "Fix Links" button appears in the posts/pages list view whenever
 * a language filter is active. Clicking it opens a modal overlay that shows
 * a dry-run scan, then lets the editor fix posts individually or all at once.
 *
 * Singleton of the admin concern — instantiated once from language-router.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSFLR_Link_Fixer {

	private Language_Router $router;

	public function __construct( Language_Router $router ) {
		$this->router = $router;
		$this->register_hooks();
	}

	// =========================================================
	// HOOKS
	// =========================================================

	private function register_hooks(): void {
		add_action( 'restrict_manage_posts',      [ $this, 'render_fix_links_button' ] );
		add_action( 'admin_footer',               [ $this, 'render_modal' ] );
		add_action( 'wp_ajax_lsflr_scan_links',   [ $this, 'ajax_scan' ] );
		add_action( 'wp_ajax_lsflr_fix_post',     [ $this, 'ajax_fix_post' ] );
	}

	// =========================================================
	// CORE: URL EXTRACTION
	// =========================================================

	/**
	 * Return every unique absolute internal URL found in href attributes.
	 *
	 * Handles:
	 *  - Absolute internal URLs  (https://example.com/…)
	 *  - Root-relative URLs      (/some-page/)
	 *
	 * Fragment-only (#) and protocol-relative (//) links are skipped.
	 *
	 * @return string[] Absolute internal URLs, de-duplicated.
	 */
	private function extract_internal_links( string $content ): array {
		if ( ! preg_match_all( '/<a\s[^>]*\bhref="([^"#][^"]*)"[^>]*>/i', $content, $matches ) ) {
			return [];
		}

		$home  = home_url();    // e.g. https://example.com
		$links = [];

		foreach ( $matches[1] as $url ) {
			$url = trim( $url );

			if ( str_starts_with( $url, $home ) ) {
				// Already absolute internal.
				$links[] = $url;
				continue;
			}

			// Root-relative but not protocol-relative.
			if ( $url[0] === '/' && ( strlen( $url ) < 2 || $url[1] !== '/' ) ) {
				$links[] = $home . $url;
			}
		}

		return array_values( array_unique( $links ) );
	}

	// =========================================================
	// CORE: URL → POST ID
	// =========================================================

	/**
	 * Resolve an absolute internal URL to its underlying WordPress post ID.
	 *
	 * Any language prefix (/es/, /de/, …) is stripped before the lookup so
	 * that both source-language URLs (/about/) and already-prefixed URLs
	 * (/es/about/) resolve to the same underlying post.
	 */
	private function resolve_to_post_id( string $url ): int {
		$home = trailingslashit( home_url() );

		// Strip a recognised language prefix if present.
		foreach ( $this->router->languages() as $lang ) {
			$prefix = $home . $lang . '/';
			if ( str_starts_with( $url, $prefix ) ) {
				$url = $home . substr( $url, strlen( $prefix ) );
				break;
			}
		}

		return (int) url_to_postid( $url );
	}

	// =========================================================
	// CORE: SCAN
	// =========================================================

	/**
	 * Analyse a single post and return the link replacements available.
	 *
	 * Only links whose target has a translation in $target_lang — and whose
	 * current href does not already point to that translation — are returned.
	 *
	 * @return array{
	 *   post_id: int,
	 *   title:   string,
	 *   fixes:   list<array{
	 *     from:              string,
	 *     to:                string,
	 *     linked_post_id:    int,
	 *     linked_post_title: string,
	 *     target_post_id:    int
	 *   }>
	 * }
	 */
	public function scan_post( int $post_id, string $target_lang ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$target_prefix = trailingslashit( home_url() ) . $target_lang . '/';
		$fixes         = [];

		foreach ( $this->extract_internal_links( $post->post_content ) as $url ) {

			// Skip if the href already carries the target-language prefix.
			if ( str_starts_with( $url, $target_prefix ) ) {
				continue;
			}

			$linked_id = $this->resolve_to_post_id( $url );
			if ( ! $linked_id ) {
				continue;
			}

			$translations = $this->router->get_translations( $linked_id );
			if ( empty( $translations[ $target_lang ] ) ) {
				continue; // No translation exists for this language — leave the link alone.
			}

			$target_id = (int) $translations[ $target_lang ];
			if ( $target_id === $linked_id ) {
				continue; // The linked post is already the target-language version.
			}

			$new_url = get_permalink( $target_id );
			if ( ! $new_url || $new_url === $url ) {
				continue;
			}

			$fixes[] = [
				'from'              => $url,
				'to'                => $new_url,
				'linked_post_id'    => $linked_id,
				'linked_post_title' => get_the_title( $linked_id ),
				'target_post_id'    => $target_id,
			];
		}

		return [
			'post_id' => $post_id,
			'title'   => $post->post_title,
			'fixes'   => $fixes,
		];
	}

	// =========================================================
	// CORE: FIX
	// =========================================================

	/**
	 * Apply all available link fixes to a single post and persist the result.
	 *
	 * @return array{ applied: int }
	 */
	public function fix_post( int $post_id, string $target_lang ): array {
		$scan    = $this->scan_post( $post_id, $target_lang );
		$post    = get_post( $post_id );
		$content = $post->post_content;
		$applied = 0;

		foreach ( $scan['fixes'] as $fix ) {
			$count    = substr_count( $content, $fix['from'] );
			$content  = str_replace( $fix['from'], $fix['to'], $content );
			$applied += $count;
		}

		if ( $applied > 0 ) {
			// Temporarily unhook handle_save_post so that this content-only update
			// does not corrupt translation metadata: TRID assignments, language
			// timestamps, and the outdated flag must remain exactly as they were.
			remove_action( 'wp_after_insert_post', [ $this->router, 'handle_save_post' ], 10 );
			remove_action( 'wp_after_insert_post', [ $this->router, 'handle_cache_clear' ], 20 );

			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => $content,
			] );

			add_action( 'wp_after_insert_post', [ $this->router, 'handle_save_post' ], 10, 2 );
			add_action( 'wp_after_insert_post', [ $this->router, 'handle_cache_clear' ], 20 );
		}

		return [ 'applied' => $applied ];
	}

	// =========================================================
	// AJAX: SCAN (dry-run for a whole language)
	// =========================================================

	public function ajax_scan(): void {
		check_ajax_referer( 'lsflr_link_fixer_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$lang = sanitize_text_field( $_POST['lang'] ?? '' );
		if ( ! $this->router->is_valid_lang( $lang ) ) {
			wp_send_json_error( 'Invalid language' );
		}

		$query = new WP_Query( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => '_lang', 'value' => $lang ] ],
		] );

		$results = [];
		foreach ( $query->posts as $post_id ) {
			$scan = $this->scan_post( (int) $post_id, $lang );
			if ( ! empty( $scan['fixes'] ) ) {
				$results[] = $scan;
			}
		}

		wp_send_json_success( [
			'lang'    => $lang,
			'results' => $results,
			'total'   => count( $results ),
		] );
	}

	// =========================================================
	// AJAX: FIX SINGLE POST
	// =========================================================

	public function ajax_fix_post(): void {
		check_ajax_referer( 'lsflr_link_fixer_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$lang    = sanitize_text_field( $_POST['lang'] ?? '' );

		if ( ! $post_id || ! $this->router->is_valid_lang( $lang ) ) {
			wp_send_json_error( 'Invalid parameters' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied for this post' );
		}

		$result = $this->fix_post( $post_id, $lang );
		wp_send_json_success( $result );
	}

	// =========================================================
	// ADMIN UI: BUTTON (in the toolbar above the post list)
	// =========================================================

	/**
	 * Render the "Fix Links" button next to the language filter dropdown.
	 * Only shown when a language filter is currently active.
	 */
	public function render_fix_links_button( string $post_type ): void {
		if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
			return;
		}

		$lang = $this->active_lang_filter();
		if ( ! $lang ) {
			return;
		}

		$nonce = wp_create_nonce( 'lsflr_link_fixer_nonce' );
		printf(
			'<button type="button" class="button lsflr-open-fixer" data-lang="%s" data-nonce="%s">'
			. '🔗 Fix Links (%s)'
			. '</button>',
			esc_attr( $lang ),
			esc_attr( $nonce ),
			strtoupper( $lang )
		);
	}

	// =========================================================
	// ADMIN UI: MODAL OVERLAY + JS
	// =========================================================

	/**
	 * Output the modal markup, styles, and JavaScript.
	 * Only injected on the post/page list screen.
	 */
	public function render_modal(): void {
		global $pagenow;
		if ( $pagenow !== 'edit.php' )       return;
		if ( ! current_user_can( 'edit_posts' ) ) return;
		?>

		<!-- LSFLR Link Fixer modal -->
		<div id="lsflr-fixer-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="lsflr-fixer-title">
			<div id="lsflr-fixer-modal">

				<button id="lsflr-fixer-close" type="button" title="<?php esc_attr_e( 'Close' ); ?>">✕</button>

				<h2 id="lsflr-fixer-title">🔗 <?php esc_html_e( 'Internal Link Fixer' ); ?></h2>

				<p id="lsflr-fixer-status"></p>

				<div id="lsflr-fixer-results"></div>

				<div id="lsflr-fixer-actions" style="display:none">
					<button id="lsflr-fix-all" type="button" class="button button-primary">
						<?php esc_html_e( 'Fix All' ); ?>
					</button>
					<span id="lsflr-fix-progress"></span>
				</div>

			</div>
		</div>

		<style>
		#lsflr-fixer-overlay {
			position: fixed;
			inset: 0;
			background: rgba(0, 0, 0, .55);
			z-index: 100000;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		#lsflr-fixer-modal {
			position: relative;
			background: #fff;
			border-radius: 6px;
			padding: 28px 32px;
			width: min(860px, 92vw);
			max-height: 82vh;
			overflow-y: auto;
			box-shadow: 0 8px 40px rgba(0, 0, 0, .25);
		}
		#lsflr-fixer-modal h2 { margin-top: 0; }

		#lsflr-fixer-close {
			position: absolute;
			top: 12px;
			right: 16px;
			background: none;
			border: none;
			font-size: 20px;
			line-height: 1;
			cursor: pointer;
			color: #666;
			padding: 0;
		}
		#lsflr-fixer-close:hover { color: #000; }

		/* ---- Results table ---- */
		#lsflr-fixer-results table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 16px;
			font-size: 13px;
		}
		#lsflr-fixer-results th {
			background: #f6f7f7;
			text-align: left;
			padding: 8px 10px;
			border-bottom: 2px solid #dcdcde;
		}
		#lsflr-fixer-results td {
			padding: 8px 10px;
			border-bottom: 1px solid #f0f0f1;
			vertical-align: top;
		}
		#lsflr-fixer-results tr.lsflr-fixed  td { background: #edfaee; }
		#lsflr-fixer-results tr.lsflr-failed td { background: #fdf0f0; }

		/* ---- Link-pair display ---- */
		.lsflr-fix-pair {
			font-family: monospace;
			font-size: 11px;
			line-height: 1.6;
			margin-bottom: 4px;
			word-break: break-all;
		}
		.lsflr-fix-pair .lsflr-from { color: #c0392b; }
		.lsflr-fix-pair .lsflr-to   { color: #27ae60; }

		/* ---- Actions bar ---- */
		#lsflr-fixer-actions {
			display: flex;
			align-items: center;
			gap: 14px;
			margin-top: 22px;
			padding-top: 16px;
			border-top: 1px solid #f0f0f1;
		}
		#lsflr-fix-progress { color: #555; font-size: 13px; }

		.lsflr-spinner {
			display: inline-block;
			width: 18px;
			height: 18px;
			border: 2px solid #ccc;
			border-top-color: #2271b1;
			border-radius: 50%;
			animation: lsflr-spin .7s linear infinite;
			vertical-align: middle;
			margin-right: 6px;
		}
		@keyframes lsflr-spin { to { transform: rotate(360deg); } }
		</style>

		<script>
		(function ($) {
			'use strict';

			var overlay   = $('#lsflr-fixer-overlay');
			var status    = $('#lsflr-fixer-status');
			var results   = $('#lsflr-fixer-results');
			var actions   = $('#lsflr-fixer-actions');
			var fixAllBtn = $('#lsflr-fix-all');
			var progress  = $('#lsflr-fix-progress');

			var scanData = null;   // last scan response
			var activeLang  = '';
			var activeNonce = '';

			// ---- Open ----
			$(document).on('click', '.lsflr-open-fixer', function () {
				activeLang  = $(this).data('lang');
				activeNonce = $(this).data('nonce');

				// Reset state
				scanData = null;
				results.empty();
				actions.hide();
				fixAllBtn.prop('disabled', false);
				progress.text('');

				status.html('<span class="lsflr-spinner"></span> Scanning posts for broken language links…');
				overlay.css('display', 'flex');

				doScan();
			});

			// ---- Close: button or backdrop click ----
			$(document).on('click', '#lsflr-fixer-close', function () {
				overlay.hide();
			});
			overlay.on('click', function (e) {
				if (e.target === this) overlay.hide();
			});
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape') overlay.hide();
			});

			// ---- Scan ----
			function doScan() {
				$.post(ajaxurl, {
					action : 'lsflr_scan_links',
					lang   : activeLang,
					nonce  : activeNonce
				}, function (resp) {
					if (!resp.success) {
						status.text('Scan failed: ' + (resp.data || 'unknown error'));
						return;
					}
					scanData = resp.data;
					renderResults(scanData);
				}).fail(function () {
					status.text('Scan request failed. Please try again.');
				});
			}

			// ---- Render scan results ----
			function renderResults(data) {
				if (!data.results.length) {
					status.html('✅ No broken links found for <strong>' + esc(data.lang.toUpperCase()) + '</strong>. All internal links are already correct.');
					return;
				}

				status.html(
					'Found <strong>' + data.total + '</strong> post(s) with links that can be repointed to <strong>'
					+ esc(data.lang.toUpperCase()) + '</strong>.'
				);

				var html = '<table>'
					+ '<thead><tr>'
					+ '<th>Post</th>'
					+ '<th>Links to repoint</th>'
					+ '<th></th>'
					+ '</tr></thead><tbody>';

				data.results.forEach(function (item) {
					var pairs = item.fixes.map(function (f) {
						return '<div class="lsflr-fix-pair">'
							+ '<span class="lsflr-from">↳ ' + esc(stripHost(f.from)) + '</span><br>'
							+ '<span class="lsflr-to">→ '  + esc(stripHost(f.to))   + '</span>'
							+ '</div>';
					}).join('');

					html += '<tr id="lsflr-row-' + item.post_id + '">'
						+ '<td><strong>' + esc(item.title) + '</strong><br>'
						+ '<small style="color:#888">#' + item.post_id + ' &mdash; ' + item.fixes.length + ' link(s)</small></td>'
						+ '<td>' + pairs + '</td>'
						+ '<td style="white-space:nowrap">'
						+ '<button type="button" class="button lsflr-fix-single" data-post-id="' + item.post_id + '">Fix</button>'
						+ '</td>'
						+ '</tr>';
				});

				html += '</tbody></table>';
				results.html(html);
				actions.show();
			}

			// ---- Fix single post (row button) ----
			$(document).on('click', '.lsflr-fix-single', function () {
				var btn    = $(this);
				var postId = btn.data('post-id');
				btn.prop('disabled', true).text('Fixing…');
				doFix(postId, function (ok) {
					var row = $('#lsflr-row-' + postId);
					if (ok) { row.addClass('lsflr-fixed');  btn.text('✅ Fixed'); }
					else    { row.addClass('lsflr-failed'); btn.text('❌ Failed').prop('disabled', false); }
				});
			});

			// ---- Fix all (sequential to avoid DB contention) ----
			fixAllBtn.on('click', function () {
				if (!scanData || !scanData.results.length) return;
				fixAllBtn.prop('disabled', true);

				var queue = scanData.results.slice();
				var done  = 0;
				var total = queue.length;

				function next() {
					if (!queue.length) {
						progress.text('Done — ' + done + ' of ' + total + ' post(s) fixed.');
						return;
					}
					var item = queue.shift();
					progress.html('<span class="lsflr-spinner"></span> Fixing ' + (done + 1) + ' / ' + total + '…');

					var rowBtn = $('#lsflr-row-' + item.post_id + ' .lsflr-fix-single');
					rowBtn.prop('disabled', true).text('Fixing…');

					doFix(item.post_id, function (ok) {
						done++;
						var row = $('#lsflr-row-' + item.post_id);
						if (ok) { row.addClass('lsflr-fixed');  rowBtn.text('✅ Fixed'); }
						else    { row.addClass('lsflr-failed'); rowBtn.text('❌ Failed'); }
						next();
					});
				}

				next();
			});

			// ---- AJAX helper ----
			function doFix(postId, cb) {
				$.post(ajaxurl, {
					action  : 'lsflr_fix_post',
					post_id : postId,
					lang    : activeLang,
					nonce   : activeNonce
				}, function (resp) {
					cb(resp.success);
				}).fail(function () {
					cb(false);
				});
			}

			// ---- Utilities ----
			function stripHost(url) {
				return url.replace(/^https?:\/\/[^/]+/, '');
			}

			function esc(s) {
				return String(s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;');
			}

		}(jQuery));
		</script>

		<?php
	}

	// =========================================================
	// HELPERS
	// =========================================================

	/**
	 * Return the language currently chosen in the admin list filter,
	 * or an empty string when no filter is active.
	 */
	private function active_lang_filter(): string {
		if ( ! empty( $_GET['my_lang_filter'] ) ) {
			$lang = sanitize_text_field( $_GET['my_lang_filter'] );
			return $this->router->is_valid_lang( $lang ) ? $lang : '';
		}

		// Fall back to the persisted preference for the current user.
		$lang = (string) get_user_meta( get_current_user_id(), 'my_lang_filter', true );
		return ( $lang && $this->router->is_valid_lang( $lang ) ) ? $lang : '';
	}
}
