<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with tooltip, archive functionality, caching, and improved features.
Version: 2.9
Author: ChatGPT & Nuvorix.com
*/

// Register Custom Post Type with Metabox for Tooltip and Abbreviation Full Form
function create_glossary_post_type() {
    $labels = array(
        'name'                  => _x('Glossary', 'Post Type General Name', 'text_domain'),
        'singular_name'         => _x('Term', 'Post Type Singular Name', 'text_domain'),
        'menu_name'             => __('Glossary', 'text_domain'),
        'add_new_item'          => __('Add New Glossary Term', 'text_domain'),
        'edit_item'             => __('Edit Glossary Term', 'text_domain'),
        'view_item'             => __('View Glossary Term', 'text_domain'),
        'all_items'             => __('All Glossary Terms', 'text_domain'),
        'search_items'          => __('Search Glossary Terms', 'text_domain'),
        'not_found'             => __('No Glossary Terms found.', 'text_domain'),
    );

    // Set custom capabilities for glossary post type
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
        'label'                 => __('Glossary', 'text_domain'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor'),
        'public'                => true,
        'show_ui'               => true,
        'has_archive'           => true,
        'rewrite'               => array('slug' => 'glossary'),
        'capability_type'       => 'post',
        'capabilities'          => $capabilities,
        'map_meta_cap'          => true,
    );
    register_post_type('glossary', $args);
}
add_action('init', 'create_glossary_post_type');

// Add Meta Boxes for Tooltip and Abbreviation Full Form
function glossary_add_meta_box() {
    add_meta_box('glossary_tooltip_text', __('Tooltip Text (300 characters)', 'text_domain'), 'glossary_meta_box_callback', 'glossary', 'normal', 'high');
    add_meta_box('glossary_abbreviation_full_form', __('Abbreviation Full Form', 'text_domain'), 'glossary_abbreviation_meta_box_callback', 'glossary', 'normal', 'high');
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

// Save Meta Box Data with error handling
function glossary_save_meta_box_data($post_id) {
    if (!isset($_POST['glossary_tooltip_nonce']) || !wp_verify_nonce($_POST['glossary_tooltip_nonce'], 'save_tooltip_text')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    try {
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
    } catch (Exception $e) {
        error_log('Error saving meta box data: ' . $e->getMessage());
        echo '<p>There was an error saving the data.</p>';
    }

    // Clear cached glossary terms after saving
    wp_cache_delete('glossary_terms_cache');
}
add_action('save_post', 'glossary_save_meta_box_data');

// Define and use $glossary_terms correctly for archive
function display_glossary_terms() {
    // Set up WP_Query to fetch glossary terms
    $args = array(
        'post_type' => 'glossary',
        'posts_per_page' => 10,
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
    );
    $glossary_terms = new WP_Query($args);

    // Check if there are posts and display them
    if ($glossary_terms->have_posts()) {
        while ($glossary_terms->have_posts()) {
            $glossary_terms->the_post();
            // Display glossary terms here
            echo '<h2>' . get_the_title() . '</h2>';
            the_excerpt();
        }

        // Display pagination
        echo paginate_links(array(
            'total' => $glossary_terms->max_num_pages,
        ));
    } else {
        echo 'No glossary terms found.';
    }

    // Restore original Post Data
    wp_reset_postdata();
}

// Add shortcode to display glossary terms
add_shortcode('display_glossary_terms', 'display_glossary_terms');

// Add Tooltip Functionality with Caching
function glossary_tooltip_filter($content) {
    if (is_post_type_archive('glossary') || is_singular('glossary')) {
        return $content;
    }

    // Generate a unique cache key based on post ID
    $post_id = get_the_ID();
    $cache_key = 'glossary_tooltips_' . $post_id;

    // Try to get cached glossary tooltips
    $cached_tooltips = get_transient($cache_key);
    if ($cached_tooltips !== false) {
        return $cached_tooltips;
    }

    try {
        // Cache glossary terms to reduce database queries
        $terms = wp_cache_get('glossary_terms_cache');
        if ($terms === false) {
            $terms = get_posts(array(
                'post_type' => 'glossary',
                'posts_per_page' => -1, // Get all terms for tooltip purposes
            ));
            wp_cache_set('glossary_terms_cache', $terms, '', 72 * HOUR_IN_SECONDS);
        }

        foreach ($terms as $term) {
            $term_title = $term->post_title;
            $tooltip_text = get_post_meta($term->ID, '_tooltip_text', true) ?: 'No description available';

            // Ensure tooltip text is safe and create replacement for each term
            $tooltip_text = esc_attr(strip_tags($tooltip_text)); 
            $link = esc_url(get_permalink($term->ID));

            $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/'; 
            $replacement = '<a href="' . $link . '" target="_blank"><span class="glossary-tooltip" data-tooltip="' . $tooltip_text . '">' . esc_html($term_title) . '</span></a>';
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Cache the updated content with tooltips
        set_transient($cache_key, $content, 72 * HOUR_IN_SECONDS);
    } catch (Exception $e) {
        error_log('Error generating tooltips: ' . $e->getMessage());
    }

    return $content;
}
add_filter('the_content', 'glossary_tooltip_filter');

// Enqueue Tooltip Styles with Yellow Border
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

// Clear Cache when a Glossary Post is Updated
function clear_glossary_term_cache($post_id) {
    if (get_post_type($post_id) == 'glossary') {
        wp_cache_delete('glossary_terms_cache');
    }
    // Clear cache for individual posts when glossary is updated
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_glossary_tooltips_%'");
}
add_action('save_post', 'clear_glossary_term_cache');

// Forcefully Add and Check Glossary Capabilities for Administrator
function ensure_admin_glossary_capabilities() {
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('publish_glossaries');
        $role->add_cap('edit_glossaries');
        $role->add_cap('edit_others_glossaries');
        $role->add_cap('delete_glossaries');
        $role->add_cap('delete_others_glossaries');
        $role->add_cap('read_private_glossaries');
        $role->add_cap('edit_glossary');
        $role->add_cap('delete_glossary');
        $role->add_cap('read_glossary');
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
