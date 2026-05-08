<?php
/**
 * Plugin Name: MU Meta Description (Full Control)
 * Description: Adds a meta box for meta descriptions + outputs meta/OG/Twitter. Fallback: excerpt → site description.
 */

// --- Meta Box UI ---
add_action('add_meta_boxes', function () {

    foreach (get_post_types(['public' => true], 'names') as $type) {

        add_meta_box(
            'mu_meta_description',
            'Meta Description',
            'mu_meta_description_box',
            $type,
            'normal',
            'high',
            [
                '__block_editor_compatible_meta_box' => true,
            ]
        );

    }

});

function mu_meta_description_box($post) {
    $custom = get_post_meta($post->ID, 'meta_description', true);

    // If no custom value, preload excerpt
    if (!empty($custom)) {
        $value = $custom;
        $source = 'custom';
    } else {
        $excerpt = $post->post_excerpt;

        if (empty($excerpt)) {
            // fallback to trimmed content if no excerpt exists
            $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 25, '...');
        }

        $value = $excerpt;
        $source = 'excerpt';
    }

    wp_nonce_field('mu_meta_description_save', 'mu_meta_description_nonce');

    echo '<p><label for="mu_meta_description_field"><strong>Meta Description</strong></label></p>';

    echo '<textarea id="mu_meta_description_field" name="mu_meta_description_field" rows="3" style="width:100%;">' . esc_textarea($value) . '</textarea>';

    echo '<p style="margin-top:6px;font-size:12px;color:#666;">';

    if ($source === 'custom') {
        echo 'Using custom meta description.';
    } else {
        echo 'Prefilled from excerpt. Edit to override.';
    }

    echo '</p>';
}

// --- Save Meta ---
add_action('save_post', function ($post_id) {

    if (!isset($_POST['mu_meta_description_nonce']) ||
        !wp_verify_nonce($_POST['mu_meta_description_nonce'], 'mu_meta_description_save')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['mu_meta_description_field'])) {
        $value = sanitize_textarea_field($_POST['mu_meta_description_field']);
        update_post_meta($post_id, 'meta_description', $value);
    }
});

// --- Output in <head> ---
add_action('wp_head', function () {

    if (is_admin()) return;

    $description = '';

    // 1. Custom field
    if (is_singular()) {
        $custom = get_post_meta(get_the_ID(), 'meta_description', true);
        if (!empty($custom)) {
            $description = $custom;
        }
    }

    // 2. Excerpt fallback
    if (empty($description) && is_singular()) {
        $excerpt = get_the_excerpt();
        if (!empty($excerpt)) {
            $description = $excerpt;
        }
    }

    // 3. Site description fallback
    if (empty($description)) {
        $description = get_bloginfo('description');
    }

    // Cleanup
    $description = wp_strip_all_tags($description);
    $description = trim($description);

	// Only shorten automatic fallback descriptions
	if (
		empty($custom)
		&& mb_strlen($description) > 190
	) {
		$description = mb_substr($description, 0, 187) . '...';
	}	
	
    if (empty($description)) return;

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";

}, 1);