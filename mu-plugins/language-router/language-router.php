<?php
/**
 * Plugin Name: Multilanguage Router
 * Author: Uli Hake 
 */

// =============================
// REGISTER META (GUTENBERG SAFE)
// =============================
require_once __DIR__ . '/lang-switcher-for-language-router.php';

add_action('init', function(){

    register_post_meta('', '_lang', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('', '_trid', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('', '_source_updated_at', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('', '_translation_source_updated_at', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
    ]);

});

// =============================
// CONFIG
// =============================
//function my_default_language(){ return 'ca'; }
function my_source_language(){
    return apply_filters('my_primary_language', 'ca'); // ← ONLY CONFIG you maintain
}

define('MY_LANG', my_is_valid_lang(my_detect_lang()) 
    ? my_detect_lang() 
    : my_source_language()
);

// Important to check for db necessesities...
define('MY_LANG_ROUTER_VERSION', '1.1');

function my_languages(){

    // Get installed WP languages
    $locales = get_available_languages();

    // Always include current site locale
    $locales[] = get_locale();

    $langs = [];

    foreach($locales as $locale){
        $langs[] = strtolower(substr($locale, 0, 2));
    }

    // Ensure source language is included
    $langs[] = my_source_language();
	
	// Allow to apply a filter
	return apply_filters('my_languages_list', array_values(array_unique($langs)));
    //return array_values(array_unique($langs));
}

// =============================
// LANGUAGE DETECTION
// =============================
function my_locale_from_lang($lang){

    static $cache = [];

    if(isset($cache[$lang])) return $cache[$lang];

    $lang = strtolower($lang);

    // 🔥 1. HARD OVERRIDES (for compatibility like Vik Booking)
    $force = apply_filters('my_lang_force_locale', [
        'ca' => 'ca', // REQUIRED for Vik Booking
    ]);

    if (isset($force[$lang])) {
        return $cache[$lang] = $force[$lang];
    }

    // 2. Installed languages FIRST
    foreach (get_available_languages() as $locale) {
        $locale_l = strtolower($locale);

        if (
            $locale_l === $lang ||
            strpos($locale_l, $lang . '_') === 0
        ) {
            return $cache[$lang] = $locale;
        }
    }

    // 3. Fallback map (only if nothing installed)
    $fallback_map = apply_filters('my_lang_fallback_map', [
		'ca' => 'ca', // Vik Booking needs it like this and not ca_ES
        'en' => 'en_US',
        'es' => 'es_ES',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
    ]);

    if (isset($fallback_map[$lang])) {
        return $cache[$lang] = $fallback_map[$lang];
    }

    // 4. Default fallback
    return $cache[$lang] = apply_filters('my_lang_default_fallback', 'en_US');
}

function my_language_label($lang){

    $locale = my_locale_from_lang($lang);

    if(function_exists('locale_get_display_language')){
        $label = locale_get_display_language($locale, $locale);

        // Normalize: uppercase first character (UTF-8 safe)
        return mb_convert_case($label, MB_CASE_TITLE, "UTF-8");
    }

    // fallback
    return strtoupper($lang);
}

function my_detect_lang(){

    $langs = my_languages();
    $default = my_source_language();

    // =============================
    // 1. URL (strongest signal)
    // =============================
    $uri = trim($_SERVER['REQUEST_URI'] ?? '/', '/');
    $seg = explode('/', $uri);

    if (!empty($seg[0])) {
        $url_lang = strtolower($seg[0]);

        if (in_array($url_lang, $langs, true)) {
            return $url_lang;
        }
    }

    // =============================
    // 2. COOKIE (validated + normalized)
    // =============================
    if (!empty($_COOKIE['my_lang'])) {

        $cookie_lang = strtolower(trim($_COOKIE['my_lang']));

        // normalize (de-DE → de)
        if (strpos($cookie_lang, '-') !== false) {
            $cookie_lang = substr($cookie_lang, 0, 2);
        }

        if (in_array($cookie_lang, $langs, true)) {
            return $cookie_lang;
        }
    }

    // =============================
    // 3. FALLBACK
    // =============================
    return $default;
}

function my_apply_locale(){

    if (is_admin()) return;
    if (!defined('MY_LANG')) return;

    if (!isset($GLOBALS['wp_locale_switcher'])) {
        $GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
        $GLOBALS['wp_locale_switcher']->init();
    }

    $locale = my_locale_from_lang(MY_LANG);

    if ($locale !== get_locale()) {
        switch_to_locale($locale);
    }

}

add_action('plugins_loaded', 'my_apply_locale', 0);

// =============================
// LOAD TRANSLATION FILES FOR A GIVEN TEXT DOMAIN
// NEEDS TO RUN EARLY, TO BE EFFECTIVE
// =============================
add_action('init', function () {

    $locale = determine_locale(); // or your router if needed

    // Force ca instead of ca_ES if needed
    if ($locale === 'ca_ES') {
        $locale = 'ca';
    }
	// example for Vik Booking
    $mofile = WPMU_PLUGIN_DIR . '/language-router/languages/vikbooking-' . $locale . '.mo';
    if (file_exists($mofile)) {
        load_textdomain('vikbooking', $mofile);
    }

}, 1);

// =============================
// REWRITE
// =============================
add_action('init', function(){

    $langs = implode('|', my_languages());

    add_rewrite_tag('%lang%', '(' . $langs . ')');

    // =============================
    // 🔥 PAGINATION (TOP PRIORITY)
    // =============================
    add_rewrite_rule(
        '^(' . $langs . ')/page/([0-9]+)/?$',
        'index.php?lang=$matches[1]&paged=$matches[2]',
        'top'
    );

    // =============================
    // 🔥 CATEGORY + PAGINATION
    // =============================
    add_rewrite_rule(
        '^(' . $langs . ')/category/(.+?)/page/([0-9]+)/?$',
        'index.php?lang=$matches[1]&category_name=$matches[2]&paged=$matches[3]',
        'top'
    );

    add_rewrite_rule(
        '^(' . $langs . ')/category/(.+?)/?$',
        'index.php?lang=$matches[1]&category_name=$matches[2]',
        'top'
    );
	
	// FRONT PAGE (/de/)
	add_rewrite_rule(
		'^(' . $langs . ')/?$',
		'index.php?lang=$matches[1]',
		'top'
	);

    // =============================
    // 🔥 GENERIC FALLBACK (LAST)
    // =============================
	
	// GENERIC FALLBACK
	add_rewrite_rule(
		'^(' . $langs . ')/(.+)$',
		'index.php?lang=$matches[1]&pagename=$matches[2]',
		'top'
	);
});

// =============================
// VIK BOOKING
// =============================
add_filter('locale', function($locale){

    if (is_admin()) return $locale;
    if (!defined('MY_LANG')) return $locale;
    // ToDo: Not sure, we have to test this
	//if (did_action('plugins_loaded') === 0) return $locale;	
    return my_locale_from_lang(MY_LANG);

}, 0);

add_action('init', function(){

    //if (is_admin()) return;

    if (!defined('MY_LANG')) return;
	$_REQUEST['lang'] = sanitize_text_field(MY_LANG);
    //$_REQUEST['lang'] = MY_LANG;

}, 1); // ← very early

add_action('wp', function(){

    if (is_admin()) return;

    if (!defined('MY_LANG')) return;

    if (empty($_REQUEST['lang'])) {
        //$_REQUEST['lang'] = my_locale_from_lang(MY_LANG);
		$_REQUEST['lang'] = sanitize_text_field(MY_LANG);
		//$_REQUEST['lang'] = MY_LANG;
    }

});

// =============================
// TRID SYSTEM
// =============================

function my_get_trid($id){ return get_post_meta($id,'_trid',true); }
function my_set_trid($id,$v){ update_post_meta($id,'_trid',$v); }

function my_get_lang($id){
    $lang = get_post_meta($id,'_lang',true);

    if(!$lang){
        error_log("Missing _lang for post ID: $id");
        return my_source_language();
    }

    return $lang;
}

function my_set_lang($id,$v){ update_post_meta($id,'_lang',$v); }

// =============================
// TRANSLATIONS
// =============================
function my_get_translations($post_id){

    global $wpdb;

    $trid = my_get_trid($post_id);
    if(!$trid) return [];

    // ✅ cache by TRID (correct key)
    $cache_key = 'trid_' . $trid;

    $cached = wp_cache_get($cache_key, 'my_translations');

    if ($cached !== false) {
        return $cached;
    }

    // 🔥 original query (unchanged)
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT post_id, meta_value lang
        FROM $wpdb->postmeta
        WHERE meta_key='_lang'
        AND post_id IN (
            SELECT post_id FROM $wpdb->postmeta
            WHERE meta_key='_trid' AND meta_value=%s
        )
    ", $trid));

    $out = [];
    foreach($rows as $r){
        $out[$r->lang] = $r->post_id;
    }

    // ✅ cache result
    wp_cache_set($cache_key, $out, 'my_translations', 3600);

    return $out;
}
// =============================
// OUTDATED SYSTEM
// =============================

function my_mark_source_updated($post_id){
    update_post_meta($post_id,'_source_updated_at', time());
}

function my_mark_translation_synced($post_id){
    $source_time = get_post_meta($post_id,'_source_updated_at',true);
    update_post_meta($post_id,'_translation_source_updated_at', $source_time);
}

function my_is_outdated($post_id){

    $lang = my_get_lang($post_id);

    // Default language is NEVER outdated
    if($lang === my_source_language()){
        return false;
    }

    $source = get_post_meta($post_id,'_source_updated_at',true);
    $trans  = get_post_meta($post_id,'_translation_source_updated_at',true);

    // No source → nothing to compare
    if(!$source) return false;

    // No translation timestamp → outdated
    if(!$trans) return true;

    return intval($trans) < intval($source);
}

// =============================
// MISSING TRANSLATIONS SYSTEM
// =============================
function my_get_missing_languages($post_id) {

    $translations = my_get_translations($post_id);
    $existing = array_keys($translations);

    $current = my_get_lang($post_id);

    $missing = [];

    foreach(my_languages() as $lang){

        // ❗ skip current language
        if($lang === $current) continue;

        if(!in_array($lang, $existing)){
            $missing[] = $lang;
        }
    }

    return $missing;
}
// =============================
// QUERY FILTER (SAFE)
// =============================

add_action('pre_get_posts', function($q){

    if(is_admin() || !$q->is_main_query()) return;

    // ONLY archives
	if ($q->is_archive() || $q->is_home() || $q->is_singular()) {
        $meta_query = $q->get('meta_query') ?: [];

        $meta_query[] = [
            'key' => '_lang',
            'value' => MY_LANG
        ];

        $q->set('meta_query', $meta_query);
    }

});

// =============================
// QUERY WRAPPERS
// =============================

function my_query($args = []) {

    if (!empty($args['meta_query'])) {
        foreach ($args['meta_query'] as $mq) {
            if (isset($mq['key']) && $mq['key'] === '_lang') {
                return new WP_Query($args);
            }
        }
    }

    $args['meta_query'][] = [
        'key' => '_lang',
        'value' => MY_LANG
    ];

    return new WP_Query($args);
}

function my_query_fallback($args = []) {

    $args['meta_query'][] = [
        'relation' => 'OR',
        [
            'key' => '_lang',
            'value' => MY_LANG
        ],
        [
            'key' => '_lang',
            'value' => my_source_language()
        ]
    ];

    return new WP_Query($args);
}

function my_get_posts($args = [], $fallback = false) {
    $q = $fallback ? my_query_fallback($args) : my_query($args);
    return $q->posts;
}

// =============================
// REDIRECT + FALLBACK
// =============================

add_action('template_redirect', function(){

    if(!is_singular()) return;

    global $post;

    $translations = my_get_translations($post->ID);

    if(MY_LANG === my_source_language()) return;

    if(!empty($translations[MY_LANG])){

        if($translations[MY_LANG] != $post->ID){
            wp_redirect(get_permalink($translations[MY_LANG]),301);
            exit;
        }

    } else {
        define('MY_LANG_FALLBACK_ACTIVE', true);
    }

});

// =============================
// HOMEPAGE HANDLING
// =============================

add_action('template_redirect', function(){

    if (!is_front_page()) return;

    $front_id = get_option('page_on_front');
    if (!$front_id) return;

    $translations = my_get_translations($front_id);

    if (!empty($translations[MY_LANG])) {

        if ($translations[MY_LANG] != $front_id) {
            wp_redirect(get_permalink($translations[MY_LANG]), 301);
            exit;
        }
    }

});

// =============================
// MENU TRANSLATION
// =============================

add_filter('wp_nav_menu_objects', function($items){

    foreach($items as &$item){

        if(!empty($item->object_id)){

            $translations = my_get_translations($item->object_id);

            if(!empty($translations[MY_LANG])){
                $item->url = get_permalink($translations[MY_LANG]);
            }
        }

    }

    return $items;

});

// =============================
// ADMIN: LANGUAGE BOX
// =============================

add_action('add_meta_boxes', function(){

    add_meta_box('my_lang','Language',function($post){

        $cur = my_get_lang($post->ID);

        echo '<select name="my_lang">';
        foreach(my_languages() as $l){
            echo '<option value="'.$l.'" '.selected($cur,$l,false).'>'.strtoupper($l).'</option>';
        }
        echo '</select>';

    },null,'side');

});

// =============================
// ADMIN: TRANSLATIONS
// =============================

add_action('add_meta_boxes', function(){

    add_meta_box('my_trans','Translations',function($post){

        $current_lang = my_get_lang($post->ID);
        $translations = my_get_translations($post->ID);

        echo '<p><strong>Current language:</strong> '.strtoupper($current_lang).'</p>';

        foreach(my_languages() as $l){

            if($l === $current_lang) continue;

            $id = $translations[$l] ?? '';

            echo '<p><strong>'.strtoupper($l).'</strong>';

            if($id && my_is_outdated($id)){
                echo ' ⚠';
            }

            echo '<br>';
			// =========================================
			// TRANSLATE FROM PRIMARY LANGUAGE ONLY
			// =========================================
			/*
			wp_dropdown_pages([
				'name'              => 'my_trans_'.$l,
				'selected'          => $id,
				'show_option_none'  => '—',
				'meta_key'          => '_lang',
				//'meta_value'        => $l
				'meta_value' => my_default_language()				
			]);
			*/
			$id = $translations[$l] ?? '';

			$args = [
				'name'              => 'my_trans_'.$l,
				'show_option_none'  => '—',
				'meta_key'          => '_lang',
				'meta_value'        => $l
			];

			// Ensure selected value is always included
			if ($id) {
				$args['include'] = [$id];
				$args['selected'] = $id;
			}

			wp_dropdown_pages($args);			
			
			echo '<br>';
			// show only if we dispose of a saved language equivalent
			if (!empty($id)) {
				echo '<button type="button" class="button my-import" data-lang="'.$l.'">Override</button>';
			}
			//echo '<button type="button" class="button my-import" data-lang="'.$l.'">Override</button>';

            echo '</p>';
        }

    },null,'side');

});

// =============================
// SAVE HANDLER
// =============================
add_action('wp_after_insert_post', function($post_id, $post){

    if (!in_array($post->post_type, ['post','page'])) return;
    if (wp_is_post_revision($post_id)) return;
    if (wp_is_post_autosave($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // =============================
    // 🔹 LANGUAGE
    // =============================

    if (!(defined('DOING_AJAX') && DOING_AJAX)) {

        if (isset($_POST['my_lang']) && my_is_valid_lang($_POST['my_lang'])) {
            my_set_lang($post_id, sanitize_text_field($_POST['my_lang']));
        }
    }

    if (!my_get_lang($post_id)) {
        my_set_lang($post_id, my_source_language());
    }

    $lang = my_get_lang($post_id);

    // =============================
    // 🔹 TRID
    // =============================

    $trid = my_get_trid($post_id);

    if (!$trid) {
        $trid = wp_generate_uuid4();
        my_set_trid($post_id, $trid);
    }

    // =============================
    // 🔹 TIMESTAMPS
    // =============================

    if ($lang === my_source_language()) {

        my_mark_source_updated($post_id);

        $translations = my_get_translations($post_id);

        foreach ($translations as $t) {
            update_post_meta($t, '_translation_source_updated_at', 0);
        }

    } else {
        my_mark_translation_synced($post_id);
    }

    // =============================
    // 🔹 GROUP MERGE
    // =============================

    $group_ids = [$post_id];

    foreach(my_languages() as $l){

        if(!isset($_POST['my_trans_'.$l])) continue;

        $target_id = intval($_POST['my_trans_'.$l]);

        if(!$target_id || $target_id === $post_id) continue;

        $group_ids[] = $target_id;
    }

    $group_ids = array_unique($group_ids);

    $trid = null;

    foreach($group_ids as $pid){
        $existing = my_get_trid($pid);
        if($existing){
            $trid = $existing;
            break;
        }
    }

    if(!$trid){
        $trid = wp_generate_uuid4();
    }

    foreach($group_ids as $pid){

        my_set_trid($pid, $trid);

        if($pid == $post_id) continue;

        foreach(my_languages() as $l){
            if(isset($_POST['my_trans_'.$l]) && intval($_POST['my_trans_'.$l]) === $pid){
                my_set_lang($pid, $l);
            }
        }
    }

}, 10, 2);

// =============================
// AJAX IMPORT
// =============================

add_action('wp_ajax_my_import_translation', function(){

    $target_id = intval($_POST['post_id']);   // current post (edited)
    $lang      = sanitize_text_field($_POST['lang']);

    // Find source post (default language)
    $translations = my_get_translations($target_id);
    $source_id = $translations[my_source_language()] ?? 0;

    if(!$source_id){
        wp_send_json_error('No source found');
    }

	if($target_id === $source_id){
		wp_send_json_error('Cannot update from itself');
	}
	
    $source = get_post($source_id);
    $target = get_post($target_id);

    if(!$source || !$target){
        wp_send_json_error();
    }

	$original_lang = my_get_lang($target_id);

	wp_update_post([
		'ID'           => $target_id,
		'post_title'   => $source->post_title,
		'post_content' => $source->post_content
	]);

	// restore language
	my_set_lang($target_id, $original_lang);	

    // Sync timestamp
    $source_time = get_post_meta($source_id,'_source_updated_at',true);
    update_post_meta($target_id,'_translation_source_updated_at', $source_time);

    wp_send_json_success();

});

// =============================
// AJAX UPDATE TRANSLATION NOT SUPPORTED YET
// AND MAY NEVER BE IMPLEMENTED.
// WOULD NEED COMPLEX TRACKING OF CHANGES
// =============================


// =============================
// ADMIN JS
// =============================

add_action('admin_footer', function(){
?>
<script>
	document.addEventListener('click', function(e){

		if(!e.target.classList.contains('my-import')) return;

		if(!confirm('Override content from desired language?')) return;

		let post_id = document.getElementById('post_ID').value;
		let lang = e.target.dataset.lang;

		fetch(ajaxurl,{
			method:'POST',
			headers:{'Content-Type':'application/x-www-form-urlencoded'},
			body:new URLSearchParams({
				action:'my_import_translation',
				post_id:post_id,
				lang:lang
			})
		}).then(()=>location.reload());

	});

	// Force reload on page creation to assure language features working
	(function(){

		if(typeof wp === 'undefined' || !wp.data) return;

		let lastLang = null;

		document.addEventListener('change', function(e){

			if(e.target.name !== 'my_lang') return;

			let newLang = e.target.value;

			if(newLang === lastLang) return;
			lastLang = newLang;

			let isNew = wp.data.select('core/editor').isEditedPostNew();

			// 🟢 New post → reload (incorrect behavior)
			/*
			if(isNew){
				setTimeout(function(){
					location.reload();
				}, 300);
				return;
			}
			*/
			// Better inform
			if (isNew) {
				wp.data.dispatch('core/notices').createNotice(
					'info',
					'Language change has to be applied after saving new posts and pages first.\nPlease do a full reload after changing page language.',
					{
						type: 'snackbar',
						isDismissible: true
					}
				);

				return;
			}			
			
			// 🟢 Existing post → soft UI refresh only
			const permalink = document.querySelector('.editor-post-permalink');

			if(permalink){
				permalink.style.opacity = '0.99';
				setTimeout(()=> permalink.style.opacity = '', 50);
			}

		});

	})();	
	
</script>
<?php
});

// =============================
// ADMIN COLUMN
// =============================
add_filter('manage_posts_columns', function($cols){
    $cols['lang'] = 'Lang';
    return $cols;
});

add_filter('manage_pages_columns', function($cols){
    $cols['lang'] = 'Lang';
    return $cols;
});

add_action('manage_posts_custom_column', function($col, $id){

    if($col !== 'lang') return;

    $lang = my_get_lang($id);

    // echo '<strong>'.strtoupper($lang).'</strong>';
	echo '<strong data-lang="'.esc_attr($lang).'">'.strtoupper($lang).'</strong>';
	
    // Outdated indicator
    if(my_is_outdated($id)){
        echo ' ⚠';
    }

    // Missing translations indicator
    $missing = my_get_missing_languages($id);
	if(!empty($missing)){
		echo ' ⭕ ' . implode(',', array_map('strtoupper', $missing));
	}

}, 10, 2);

add_action('manage_pages_custom_column', function($col, $id){

    if($col !== 'lang') return;

    $lang = my_get_lang($id);

    //echo '<strong>'.strtoupper($lang).'</strong>';
	echo '<strong data-lang="'.esc_attr($lang).'">'.strtoupper($lang).'</strong>';

    // Outdated indicator
    if(my_is_outdated($id)){
        echo ' ⚠';
    }

    // Missing translations indicator
    $missing = my_get_missing_languages($id);
	if(!empty($missing)){
		echo ' ⭕ ' . implode(',', array_map('strtoupper', $missing));
	}

}, 10, 2);

// =============================
// QUICK EDIT (LANGUAGE)
// =============================

add_action('quick_edit_custom_box', function($column_name, $post_type){

    if ($column_name !== 'lang') return;

    if (!in_array($post_type, ['post','page'])) return;

    ?>
    <fieldset class="inline-edit-col">
        <label>
            <span class="title">Language</span>
            <select name="my_lang">
                <?php foreach(my_languages() as $l): ?>
                    <option value="<?php echo esc_attr($l); ?>">
                        <?php echo strtoupper($l); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </fieldset>
    <?php

}, 10, 2);

// =============================
// QUICK EDIT: POPULATE VALUE
// =============================
add_action('admin_footer', function(){
?>
<script>
		jQuery(function($){

		$(document).on('click', '.editinline', function(){

			var postId = $(this).closest('tr').attr('id').replace('post-','');

			setTimeout(function(){

				var row = $('#post-' + postId);
				var editRow = $('#edit-' + postId);

				if(!editRow.length) return;

				// 🔥 read from data attribute (not text)
				var lang = row.find('td.column-lang strong').data('lang');

				if(lang){
					editRow.find('select[name="my_lang"]').val(lang);
				}

			}, 200);

		});

	});

</script>
<?php
});

// =============================
// ADMIN FILTER: LANGUAGE
// =============================

// Dropdown in admin list
add_action('restrict_manage_posts', function($post_type){

    if (!in_array($post_type, ['post','page'])) return;

    $current = $_GET['my_lang_filter'] ?? '';

    echo '<select name="my_lang_filter">';
    echo '<option value="">All languages</option>';

    foreach (my_languages() as $lang) {
        echo '<option value="'.esc_attr($lang).'" '
            . selected($current, $lang, false)
            . '>'
            . strtoupper($lang)
            . '</option>';
    }

    echo '</select>';

});


// Apply filter to query

add_action('pre_get_posts', function($q){

    if (!is_admin() || !$q->is_main_query()) return;
    if ($q->is_front_page()) return;


    if (empty($_GET['my_lang_filter'])) return;

    $lang = sanitize_text_field($_GET['my_lang_filter']);

    $meta_query = $q->get('meta_query') ?: [];

    $meta_query[] = [
        'key'   => '_lang',
        'value' => $lang
    ];

    $q->set('meta_query', $meta_query);

});

// =============================
// ADMIN FILTER: OUTDATED
// =============================

// Dropdown
add_action('restrict_manage_posts', function($post_type){

    if (!in_array($post_type, ['post','page'])) return;

    $current = $_GET['my_outdated_filter'] ?? '';

    echo '<select name="my_outdated_filter">';
    echo '<option value="">All statuses</option>';
    echo '<option value="1" '.selected($current, '1', false).'>Outdated only</option>';
    echo '</select>';

});


// Apply filter
add_action('pre_get_posts', function($q){

    if (!is_admin() || !$q->is_main_query()) return;

    if (empty($_GET['my_outdated_filter'])) return;

    $meta_query = $q->get('meta_query') ?: [];

    // Only translations (NOT default language)
    $meta_query[] = [
        'key'     => '_lang',
        'value'   => my_source_language(),
        'compare' => '!='
    ];

    // Outdated logic
    $meta_query[] = [
        'relation' => 'OR',

        // Missing translation timestamp
        [
            'key'     => '_translation_source_updated_at',
            'compare' => 'NOT EXISTS'
        ],

        // Explicitly marked outdated
        [
            'key'     => '_translation_source_updated_at',
            'value'   => 0,
            'compare' => '='
        ]
    ];

    $q->set('meta_query', $meta_query);

});

// =============================
// PERMALINK (FRONTEND + ADMIN)
// =============================

add_filter('post_link', 'my_lang_permalink', 10, 2);
add_filter('page_link', 'my_lang_permalink', 10, 2);

function my_lang_permalink($url, $post){

    if (is_numeric($post)) {
        $post = get_post($post);
    }

    if (!$post || !isset($post->ID)) {
        return $url;
    }

    $lang = my_get_lang($post->ID);

    if (!$lang || $lang === my_source_language()) {
        return $url;
    }

    // Avoid double prefix
    if (strpos($url, '/' . $lang . '/') !== false) {
        return $url;
    }

    return home_url('/' . $lang . '/') . ltrim(parse_url($url, PHP_URL_PATH), '/');
}

// ==================================
// SEO
// ==================================
add_action('wp_head', function(){

    if (!is_singular()) return;

    global $post;

    $translations = my_get_translations($post->ID);

    foreach($translations as $lang => $id){
        $url = get_permalink($id);
        echo '<link rel="alternate" hreflang="'.esc_attr($lang).'" href="'.esc_url($url).'" />' . "\n";
    }

    // x-default
    if (!empty($translations[my_source_language()])) {
        echo '<link rel="alternate" hreflang="x-default" href="'.get_permalink($translations[my_source_language()]).'" />' . "\n";
    }

});

// ===================================
// CANONICAL LINKS (AVOID DUPLICATES)
// ===================================
add_action('wp_head', function(){

    // =============================
    // 🔹 SINGULAR (your existing logic)
    // =============================
    if (is_singular()) {

        global $post;

        if (!$post) return;

        $translations = my_get_translations($post->ID);

        if (empty($translations)) return;

        foreach($translations as $lang => $id){
            echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url(get_permalink($id)) . '" />' . "\n";
        }

        // x-default
        if (!empty($translations[my_source_language()])) {
            echo '<link rel="alternate" hreflang="x-default" href="' 
                . esc_url(get_permalink($translations[my_source_language()])) 
                . '" />' . "\n";
        }

        return;
    }

    // =============================
    // 🔹 PAGINATION
    // =============================
    if (is_paged()) {

        $paged = get_query_var('paged');

        foreach(my_languages() as $lang){

            $base = ($lang === my_source_language())
                ? home_url('/')
                : home_url('/' . $lang . '/');

            $url = ($paged > 1)
                ? $base . 'page/' . $paged . '/'
                : $base;

            echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($url) . '" />' . "\n";
        }

        return;
    }

    // =============================
    // 🔹 ARCHIVES (category, home, etc.)
    // =============================
    if (is_archive() || is_home()) {

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        foreach(my_languages() as $lang){

            if ($lang === my_source_language()) {
                $url = home_url('/' . $path . '/');
            } else {
                $url = home_url('/' . $lang . '/' . $path . '/');
            }

            echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($url) . '" />' . "\n";
        }

        return;
    }

}, 1);


add_action('init', function() {
	if (is_admin()) return;
    remove_action('wp_head', 'rel_canonical');
});

// Take care of SEO Plugins to avoid problems
// Not sure if this works, needs testing...
add_action('wp_head', function(){

    // prevent duplicates if SEO plugin active
    static $done = false;
    if ($done) return;
    $done = true;

}, 0);

// ====================================
// LANGUAGE COOKIE
// ====================================
function my_set_lang_cookie($lang){

    if (!my_is_valid_lang($lang)) return;

    setcookie(
        'my_lang',
        $lang,
        time() + MONTH_IN_SECONDS,
        '/',
        '',
        is_ssl(),
        true // httpOnly
    );
}

// =====================================
// PERFORMANCE
// =====================================
function my_ensure_lang_index(){

    global $wpdb;

    $table = $wpdb->postmeta;
    $index_name = 'idx_lang';

    // Check if index exists
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(1)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE table_schema = DATABASE()
        AND table_name = %s
        AND index_name = %s
    ", $table, $index_name));

    if ($exists) {
        return true;
    }

    // Create index
    $result = $wpdb->query("CREATE INDEX {$index_name} ON {$table} (meta_key, meta_value(10))");

    if ($result === false) {
        error_log('[Language Router] Failed to create index idx_lang');
        return false;
    }

    error_log('[Language Router] Created index idx_lang on ' . $table);

    return true;
}

add_action('plugins_loaded', function(){

    $stored = get_option('my_lang_router_version');

    if ($stored === MY_LANG_ROUTER_VERSION) {
        return;
    }

    $ok = my_ensure_lang_index();

    // Only update version if successful
    if ($ok !== false) {
        update_option('my_lang_router_version', MY_LANG_ROUTER_VERSION);
    }

}, 1);

add_action('wp_after_insert_post', function($post_id){
    my_clear_translation_cache($post_id);
}, 20);

function my_clear_translation_cache($post_id){

    $trid = my_get_trid($post_id);
    if(!$trid) return;

    wp_cache_delete('trid_' . $trid, 'my_translations');
}

// ===========================
// DEBUG
// ===========================
/*
add_action('template_redirect', function(){

    error_log('--- TEMPLATE REDIRECT ---');
    error_log('IS 404: ' . (is_404() ? 'YES' : 'NO'));

});
*/
