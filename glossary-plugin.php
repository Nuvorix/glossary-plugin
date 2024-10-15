<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with tooltip, archive functionality, caching, and improved features.
Version: 1.0 (Stable Release)
Requires at least 5.0
Tested up to 6.6.2
Author: ChatGPT & Nuvorix.com
*/

// Forhindre direkte tilgang til filen
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register Custom Post Type with Metabox for Tooltip, Abbreviation Full Form, and Alternative Meanings
function create_glossary_post_type() {
    $labels = array(
        'name'                  => _x( 'Glossary', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Term', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Glossary', 'text_domain' ),
        'add_new_item'          => __( 'Add New Glossary Term', 'text_domain' ),
        'edit_item'             => __( 'Edit Glossary Term', 'text_domain' ),
        'view_item'             => __( 'View Glossary Term', 'text_domain' ),
        'all_items'             => __( 'All Glossary Terms', 'text_domain' ),
        'search_items'          => __( 'Search Glossary Terms', 'text_domain' ),
        'not_found'             => __( 'No Glossary Terms found.', 'text_domain' ),
    );

    $capabilities = array(
        'publish_posts' => 'publish_glossaries',
        'edit_posts' => 'edit_glossaries',
        'edit_others_posts' => 'edit_others_glossaries',
        'delete_posts' => 'delete_glossaries',
        'delete_others_posts' => 'delete_others_glossaries',
        'read_private_posts' => 'read_private_glossaries',
        'edit_post' => 'edit_glossary',
        'delete_post' => 'delete_glossary',
        'read_post' => 'read_glossary',
    );

    $args = array(
        'label'                 => __( 'Glossary', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor' ),
        'public'                => true,
        'show_ui'               => true,
        'has_archive'           => true,
        'rewrite'               => array('slug' => 'glossary'),
        'capability_type'       => 'post',
        'capabilities'          => $capabilities,
        'map_meta_cap'          => true,
    );
    register_post_type( 'glossary', $args );
}
add_action( 'init', 'create_glossary_post_type' );

// Add Meta Boxes for Tooltip, Abbreviation Full Form, and Alternative Meanings
function glossary_add_meta_box() {
    add_meta_box('glossary_tooltip_text', __('Tooltip Text (300 characters)', 'text_domain'), 'glossary_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_abbreviation_full_form', __('Abbreviation Full Form', 'text_domain'), 'glossary_abbreviation_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_alternative_meanings', __('Alternative Meanings', 'text_domain'), 'glossary_alternative_meanings_meta_box_callback', 'glossary', 'normal', 'high');
}
add_action('add_meta_boxes', 'glossary_add_meta_box');

// Callback function for Tooltip Text
function glossary_meta_box_callback($post) {
    wp_nonce_field('save_tooltip_text', 'glossary_tooltip_nonce');
    $value = get_post_meta($post->ID, '_tooltip_text', true);
    echo '<textarea style="width:100%;height:100px;" id="glossary_tooltip_text" name="glossary_tooltip_text" maxlength="300">' . esc_textarea($value) . '</textarea>';
}

// Callback function for Abbreviation Full Form
function glossary_abbreviation_meta_box_callback($post) {
    wp_nonce_field('save_abbreviation_full_form', 'glossary_abbreviation_nonce');
    $value = get_post_meta($post->ID, '_abbreviation_full_form', true);
    echo '<input type="text" style="width:100%;" id="glossary_abbreviation_full_form" name="glossary_abbreviation_full_form" value="' . esc_attr($value) . '">';
}

// Callback function for Alternative Meanings meta box
function glossary_alternative_meanings_meta_box_callback($post) {
    wp_nonce_field('save_alternative_meanings', 'glossary_alternative_meanings_nonce');
    $value = get_post_meta($post->ID, '_alternative_meanings', true);
    echo '<textarea style="width:100%;height:50px;" id="glossary_alternative_meanings" name="glossary_alternative_meanings">' . esc_textarea($value) . '</textarea>';
    echo '<p>' . __('Enter alternative meanings as a comma-separated list, e.g., "Cache, Caching"', 'text_domain') . '</p>';
}

// Save Meta Box Data
function glossary_save_meta_box_data($post_id) {
    if (!isset($_POST['glossary_tooltip_nonce']) || !wp_verify_nonce($_POST['glossary_tooltip_nonce'], 'save_tooltip_text')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Save tooltip text
    if (isset($_POST['glossary_tooltip_text'])) {
        $tooltip_text = wp_kses_post($_POST['glossary_tooltip_text']);
        $tooltip_text = substr($tooltip_text, 0, 300); // Limit to 300 characters
        update_post_meta($post_id, '_tooltip_text', $tooltip_text);
    }

    // Save abbreviation full form
    if (!isset($_POST['glossary_abbreviation_nonce']) || !wp_verify_nonce($_POST['glossary_abbreviation_nonce'], 'save_abbreviation_full_form')) {
        return;
    }
    if (isset($_POST['glossary_abbreviation_full_form'])) {
        $abbreviation_full_form = sanitize_text_field($_POST['glossary_abbreviation_full_form']);
        update_post_meta($post_id, '_abbreviation_full_form', $abbreviation_full_form);
    }

    // Save alternative meanings
    if (!isset($_POST['glossary_alternative_meanings_nonce']) || !wp_verify_nonce($_POST['glossary_alternative_meanings_nonce'], 'save_alternative_meanings')) {
        return;
    }
    if (isset($_POST['glossary_alternative_meanings'])) {
        $alternative_meanings = sanitize_text_field($_POST['glossary_alternative_meanings']);
        update_post_meta($post_id, '_alternative_meanings', $alternative_meanings);
    }

    // Clear cached glossary terms after saving
    wp_cache_delete('glossary_terms_cache', 'glossary');
}
add_action('save_post', 'glossary_save_meta_box_data');

// Display Glossary Terms in Archive or Shortcode
function display_glossary_terms() {
    $args = array(
        'post_type' => 'glossary',
        'posts_per_page' => 10,
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
    );
    $glossary_terms = new WP_Query($args);

    if ($glossary_terms->have_posts()) {
        while ($glossary_terms->have_posts()) {
            $glossary_terms->the_post();
            echo '<h2>' . get_the_title() . '</h2>';
            the_excerpt();
        }

        echo paginate_links(array(
            'total' => $glossary_terms->max_num_pages,
        ));
    } else {
        echo 'No glossary terms found.';
    }

    wp_reset_postdata();
}
add_shortcode('display_glossary_terms', 'display_glossary_terms');

// Add Tooltip Functionality with Caching per page
function glossary_tooltip_filter($content) {
    if (is_post_type_archive('glossary') || is_singular('glossary')) {
        return $content;
    }

    // Cache based on content hash
    $cache_key = 'glossary_terms_cache_' . md5($content);
    $terms = wp_cache_get($cache_key, 'glossary');
    
    if ($terms === false) {
        $all_terms = get_posts(array(
            'post_type' => 'glossary',
            'posts_per_page' => -1,
        ));

        $terms_to_cache = array(); // Array to store only the terms that are found in the content

        // Loop through all terms and check if they exist in the content
        foreach ($all_terms as $term) {
            $term_title = $term->post_title;
            if (stripos($content, $term_title) !== false) {
                $terms_to_cache[] = $term; // Add to the cacheable terms if found in content
            }
        }

        // Cache the relevant terms found in the content
        wp_cache_set($cache_key, $terms_to_cache, 'glossary', 72 * HOUR_IN_SECONDS);
        $terms = $terms_to_cache;
    }

    // Apply tooltips for each relevant term
    foreach ($terms as $term) {
        $term_title = $term->post_title;
        $tooltip_text = get_post_meta($term->ID, '_tooltip_text', true) ?: 'No description available';
        $alternative_meanings = get_post_meta($term->ID, '_alternative_meanings', true);
        $meanings = $alternative_meanings ? esc_html($alternative_meanings) : '';
        $tooltip_text = esc_attr(strip_tags($tooltip_text)); // Ensure tooltip text is safe
        $link = get_permalink($term->ID);
        $tooltip = '<span class="glossary-tooltip" data-tooltip="' . esc_js($tooltip_text) . '">' . esc_html($term_title) . ($meanings ? ' (' . $meanings . ')' : '') . '</span>';
        $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/';
        $replacement = '<a href="' . esc_url($link) . '" target="_blank">' . $tooltip . '</a>';
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}
add_filter('the_content', 'glossary_tooltip_filter');

// Enqueue Tooltip Styles
function glossary_enqueue_assets() {
    echo '<style>
    .glossary-tooltip {
        border-bottom: 1px dotted #000;
        cursor: help;
        color: #0073aa;
    }
    .glossary-tooltip:hover::after {
        content: attr(data-tooltip);
        background: #111;
        color: #fff;
        border: 1px solid rgba(238, 238, 34, 0.75); /* Yellow border */
        border-radius: 8px;
        padding: 10px;
        position: absolute;
        z-index: 1000;
        white-space: normal;
        max-width: 300px;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        line-height: 1.5;
    }
    </style>';
}
add_action('wp_head', 'glossary_enqueue_assets');

// Forcefully Add and Check Glossary Capabilities for Administrator
function ensure_admin_glossary_capabilities() {
    $role = get_role('administrator');
    if ($role) {
        $capabilities = array(
            'publish_glossaries', 'edit_glossaries', 'edit_others_glossaries',
            'delete_glossaries', 'delete_others_glossaries', 'read_private_glossaries',
            'edit_glossary', 'delete_glossary', 'read_glossary'
        );
        foreach ($capabilities as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
    }
}
add_action('admin_init', 'ensure_admin_glossary_capabilities');

// Map Capabilities for Editing, Deleting, and Reading Glossary Terms
function glossary_map_meta_cap($caps, $cap, $user_id, $args) {
    if (in_array($cap, array('edit_glossary', 'delete_glossary', 'read_glossary'))) {
        $post = get_post($args[0]);
        $post_type = get_post_type_object($post->post_type);

        $caps = array();
        if ('edit_glossary' === $cap) {
            $caps[] = $user_id == $post->post_author ? $post_type->cap->edit_posts : $post_type->cap->edit_others_posts;
        } elseif ('delete_glossary' === $cap) {
            $caps[] = $user_id == $post->post_author ? $post_type->cap->delete_posts : $post_type->cap->delete_others_posts;
        } elseif ('read_glossary' === $cap) {
            $caps[] = 'private' != $post->post_status ? 'read' : $post_type->cap->read_private_posts;
        }
    }

    return $caps;
}
add_filter('map_meta_cap', 'glossary_map_meta_cap', 10, 4);

// Clear Cache when a Glossary Post is Updated
function clear_glossary_term_cache($post_id) {
    if (get_post_type($post_id) == 'glossary') {
        wp_cache_delete('glossary_terms_cache', 'glossary');
    }
}
add_action('save_post', 'clear_glossary_term_cache');
