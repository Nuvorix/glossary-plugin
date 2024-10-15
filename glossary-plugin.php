<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with tooltip, archive functionality, caching, and improved features.
Version: 1.0 (Stable Release)
Requires at least: 5.0
Tested up to: 6.6.2
Author: ChatGPT & Nuvorix.com
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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

// Add Meta Boxes for Tooltip and Abbreviation Full Form
function glossary_add_meta_box() {
    add_meta_box('glossary_tooltip_text', __('Tooltip Text (300 characters) - Shown when a user hovers over the term', 'text_domain'), 'glossary_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_abbreviation_full_form', __('Abbreviation Full Form - Shown in parentheses on the archive page', 'text_domain'), 'glossary_abbreviation_meta_box_callback', 'glossary', 'normal', 'high');
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

    // Clear cached glossary terms after saving
    wp_cache_delete('glossary_terms_cache', 'glossary');
    error_log("Glossary cache cleared after post save for post ID: " . $post_id);
}
add_action('save_post', 'glossary_save_meta_box_data');

// Display Glossary Terms in Archive or Shortcode
function display_glossary_terms() {
    $args = array(
        'post_type' => 'glossary',
        'posts_per_page' => 25,
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

    // Logging cache status
    if ($terms === false) {
        error_log("Glossary cache not found, generating new cache for content hash: " . md5($content));

        $all_terms = get_posts(array(
            'post_type' => 'glossary',
            'posts_per_page' => -1,
        ));

        $terms_to_cache = array(); // Array to store only the terms that are found in the content

        // Loop through all terms and check if they exist in the content
        foreach ($all_terms as $term) {
            $term_title = $term->post_title;
            if (stripos($content, $term_title) !== false) {
                $terms_to_cache[] = array(
                    'title' => $term->post_title,
                    'link' => get_permalink($term->ID),
                    'ID' => $term->ID // Added term ID for later use
                );
            }
        }

        // Cache the relevant terms found in the content
        wp_cache_set($cache_key, $terms_to_cache, 'glossary', 72 * HOUR_IN_SECONDS);
        set_transient('glossary_cache_list', $terms_to_cache, 72 * HOUR_IN_SECONDS);  // Update cache list transient
        $terms = $terms_to_cache;

        // Log cache creation
        error_log("Glossary cache created for content hash: " . md5($content));
    } else {
        error_log("Glossary cache found for content hash: " . md5($content));
    }

    // Apply tooltips for each relevant term
    foreach ($terms as $term_data) {
        $term_title = $term_data['title'];
        $tooltip_text = get_post_meta($term_data['ID'], '_tooltip_text', true) ?: 'No description available'; // Fetch the tooltip text
        $tooltip_text = esc_attr(strip_tags($tooltip_text)); // Ensure tooltip text is safe
        $link = $term_data['link'];
        $tooltip = '<span class="glossary-tooltip" data-tooltip="' . esc_js($tooltip_text) . '">' . esc_html($term_title) . '</span>';
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

// Forcefully Add and Check Glossary Capabilities for Administrator and Editor
function ensure_admin_glossary_capabilities() {
    $roles = ['administrator', 'editor'];
    $capabilities = array(
        'publish_glossaries', 'edit_glossaries', 'edit_others_glossaries',
        'delete_glossaries', 'delete_others_glossaries', 'read_private_glossaries',
        'edit_glossary', 'delete_glossary', 'read_glossary'
    );

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    // Optionally, remove glossary capabilities from lower roles
    $lower_roles = ['author', 'contributor', 'subscriber'];
    foreach ($lower_roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                if ($role->has_cap($cap)) {
                    $role->remove_cap($cap); // Remove capability from lower roles
                }
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
        error_log("Glossary cache cleared for post ID: " . $post_id);
    }
}
add_action('save_post', 'clear_glossary_term_cache');

// Add Glossary Cache and Glossary Info as submenus under Glossary
function glossary_add_submenu_pages() {
    add_submenu_page(
        'edit.php?post_type=glossary',  // Parent slug (Glossary menu)
        __('Glossary Cache', 'text_domain'),  // Page title
        __('Glossary Cache', 'text_domain'),  // Menu title
        'manage_options',  // Capability (administrator only)
        'glossary_cache',  // Menu slug
        'glossary_cache_page_callback'  // Callback function for displaying the page
    );

    add_submenu_page(
        'edit.php?post_type=glossary',  // Parent slug (Glossary menu)
        __('Glossary Info', 'text_domain'),  // Page title
        __('Glossary Info', 'text_domain'),  // Menu title
        'manage_options',  // Capability (administrator only)
        'glossary_info',  // Menu slug
        'glossary_info_page_callback'  // Callback function for displaying the page
    );
}
add_action('admin_menu', 'glossary_add_submenu_pages');

// Callback function for the Cache page
function glossary_cache_page_callback() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Cached Glossary Tooltips', 'text_domain') . '</h1>';
    
    $cache_list = get_transient('glossary_cache_list');

    if ($cache_list && is_array($cache_list)) {
        $total_cached = count($cache_list);
        echo '<p>' . sprintf(esc_html__('%s tooltips cached out of 1000', 'text_domain'), $total_cached) . '</p>';
        echo '<ul>';

        // Adjusting to handle the data correctly
        foreach ($cache_list as $term) {
            $term_title = esc_html($term['title']);
            $term_link = esc_url($term['link']);
            echo '<li><a href="' . $term_link . '" target="_blank">' . $term_title . '</a></li>';
        }

        echo '</ul>';
    } else {
        echo '<p>' . esc_html__('No cached glossary tooltips found.', 'text_domain') . '</p>';
    }

    // Clear Cache Button
    echo '<form method="post">';
    echo '<input type="submit" name="clear_glossary_cache" class="button button-primary" value="' . esc_attr__('Clear Cache', 'text_domain') . '">';
    echo '</form>';

    // Handle Cache Clearing
    if (isset($_POST['clear_glossary_cache'])) {
        delete_transient('glossary_cache_list');
        error_log("Glossary cache cleared manually via admin page.");
        echo '<p>' . esc_html__('Cache cleared successfully.', 'text_domain') . '</p>';
    }

    echo '</div>';
}

// Callback function for the Info page
function glossary_info_page_callback() {
    $glossary_count = wp_count_posts('glossary')->publish;
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Glossary Info', 'text_domain') . '</h1>';
    echo '<p>' . esc_html__('To use the Glossary plugin, insert the following shortcode on an archive page:', 'text_domain') . '</p>';
    echo '<code>[glossary_archive]</code>';
    echo '<p>' . sprintf(esc_html__('There are currently %s glossary terms available.', 'text_domain'), $glossary_count) . '</p>';
    echo '</div>';
}
