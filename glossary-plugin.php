<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with tooltip, archive functionality, caching, and improved features.
Version: 2.4
Author: ChatGPT & Nuvorix.com
*/

// Register Custom Post Type with Metabox for Tooltip and Abbreviation Full Form
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

    // Set custom capabilities for glossary post type
    $capabilities = array(
        'publish_posts' => 'publish_glossaries', // Custom capability for publishing glossary posts
        'edit_posts' => 'edit_glossaries', // Custom capability for editing glossary posts
        'edit_others_posts' => 'edit_others_glossaries', // Custom capability for editing others' glossary posts
        'delete_posts' => 'delete_glossaries', // Custom capability for deleting glossary posts
        'delete_others_posts' => 'delete_others_glossaries', // Custom capability for deleting others' glossary posts
        'read_private_posts' => 'read_private_glossaries', // Custom capability for reading private glossary posts
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
        'capability_type'       => 'glossary', // Custom capability type for glossary
        'capabilities'          => $capabilities,  // Apply custom capabilities
        'map_meta_cap'          => true,  // Map meta capabilities for more control
    );
    register_post_type( 'glossary', $args );
}
add_action( 'init', 'create_glossary_post_type' );

// Add Meta Boxes for Tooltip and Abbreviation Full Form
function glossary_add_meta_box() {
    add_meta_box('glossary_tooltip_text', __('Tooltip Text (300 characters)', 'text_domain'), 'glossary_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_abbreviation_full_form', __('Abbreviation Full Form', 'text_domain'), 'glossary_abbreviation_meta_box_callback', 'glossary', 'normal', 'high');
}
add_action('add_meta_boxes', 'glossary_add_meta_box');

function glossary_meta_box_callback($post) {
    wp_nonce_field('save_tooltip_text', 'glossary_tooltip_nonce');
    $value = get_post_meta($post->ID, '_tooltip_text', true);
    echo '<textarea style="width:100%;height:100px;" id="glossary_tooltip_text" name="glossary_tooltip_text" maxlength="300">' . esc_textarea($value) . '</textarea>';
}

function glossary_abbreviation_meta_box_callback($post) {
    wp_nonce_field('save_abbreviation_full_form', 'glossary_abbreviation_nonce');
    $value = get_post_meta($post->ID, '_abbreviation_full_form', true);
    echo '<input type="text" style="width:100%;" id="glossary_abbreviation_full_form" name="glossary_abbreviation_full_form" value="' . esc_attr($value) . '">';
}

// Save Meta Box Data
function glossary_save_meta_box_data($post_id) {
    if (!isset($_POST['glossary_tooltip_nonce']) || !wp_verify_nonce($_POST['glossary_tooltip_nonce'], 'save_tooltip_text')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['glossary_tooltip_text'])) {
        $tooltip_text = wp_kses_post($_POST['glossary_tooltip_text']);
        $tooltip_text = substr($tooltip_text, 0, 300); // Limit to 300 characters
        update_post_meta($post_id, '_tooltip_text', $tooltip_text);
    }

    if (!isset($_POST['glossary_abbreviation_nonce']) || !wp_verify_nonce($_POST['glossary_abbreviation_nonce'], 'save_abbreviation_full_form')) {
        return;
    }
    if (isset($_POST['glossary_abbreviation_full_form'])) {
        $abbreviation_full_form = sanitize_text_field($_POST['glossary_abbreviation_full_form']);
        update_post_meta($post_id, '_abbreviation_full_form', $abbreviation_full_form);
    }

    // Clear cached glossary terms after saving
    wp_cache_delete('glossary_terms_cache');
}
add_action('save_post', 'glossary_save_meta_box_data');

// Add Tooltip Functionality with Caching, excluding Glossary archive pages
function glossary_tooltip_filter($content) {
    if (is_post_type_archive('glossary') || is_singular('glossary')) {
        return $content;
    }

    // Cache glossary terms to reduce database queries
    $terms = wp_cache_get('glossary_terms_cache');
    if ($terms === false) {
        $terms = get_posts(array(
            'post_type' => 'glossary',
            'posts_per_page' => -1, // Get all terms for tooltip purposes
        ));
        wp_cache_set('glossary_terms_cache', $terms, '', 12 * HOUR_IN_SECONDS);
    }

    foreach ($terms as $term) {
        $term_title = $term->post_title;
        $tooltip_text = get_post_meta($term->ID, '_tooltip_text', true) ?: 'No description available';
        $tooltip_text = esc_attr(strip_tags($tooltip_text)); // Ensure tooltip text is safe
        $link = get_permalink($term->ID);
        $tooltip = '<span class="glossary-tooltip" data-tooltip="' . esc_attr($tooltip_text) . '">' . esc_html($term_title) . '</span>';
        $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/';
        $replacement = '<a href="' . esc_url($link) . '" target="_blank">' . $tooltip . '</a>';
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}
add_filter('the_content', 'glossary_tooltip_filter');

// Enqueue Tooltip Styles with Yellow Border
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

// Clear Cache when a Glossary Post is Updated
function clear_glossary_term_cache($post_id) {
    if (get_post_type($post_id) == 'glossary') {
        wp_cache_delete('glossary_terms_cache');
    }
}
add_action('save_post', 'clear_glossary_term_cache');
