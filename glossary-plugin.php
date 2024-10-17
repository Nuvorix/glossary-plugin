<?php
/*
Plugin Name: Glossary Plugin
Description: A custom glossary plugin with tooltip, archive functionality, and improved features.
Version: 1.0 (Stable Release)
Requires at least: 5.0
Tested up to: 6.6.2
Author: ChatGPT & Nuvorix.com
Notes: If you expect more than 100 visitors or a lot of glossaries, please consider using a caching plugin, e.g. LiteSpeed Cache.
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register Custom Post Type with Metabox for Tooltip and Abbreviation Full Form
function create_glossary_post_type() {
    $labels = array(
    'name'               => _x( 'Glossary', 'Post Type General Name', 'text_domain' ),
    'singular_name'      => _x( 'Term', 'Post Type Singular Name', 'text_domain' ),
    'menu_name'          => __( 'Glossary', 'text_domain' ),
    'add_new'            => __( 'Add New Glossary', 'text_domain' ), // For "Add New" button in the menu
    'add_new_item'       => __( 'Add New Glossary Term', 'text_domain' ), // For form heading when adding new term
    'edit_item'          => __( 'Edit Glossary Term', 'text_domain' ),
    'view_item'          => __( 'View Glossary Term', 'text_domain' ),
    'all_items'          => __( 'All Glossaries', 'text_domain' ),
    'search_items'       => __( 'Search Glossary Terms', 'text_domain' ),
    'not_found'          => __( 'No Glossary Terms found.', 'text_domain' ),
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

// Enqueue external CSS file for glossary tooltips
function glossary_enqueue_assets() {
    wp_enqueue_style('glossary-tooltips', plugin_dir_url(__FILE__) . 'css/glossary-tooltips.css');
}
add_action('wp_enqueue_scripts', 'glossary_enqueue_assets');

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

// Add Tooltip Functionality for glossary terms found in the content
function glossary_tooltip_filter($content) {
    if (is_post_type_archive('glossary') || is_singular('glossary')) {
        return $content;
    }

    // Retrieve all glossary terms
    $all_terms = get_posts(array(
        'post_type' => 'glossary',
        'posts_per_page' => -1,
    ));

    // Apply tooltips for each relevant term
    foreach ($all_terms as $term) {
        $term_title = $term->post_title;
        $tooltip_text = get_post_meta($term->ID, '_tooltip_text', true) ?: 'No description available'; // Fetch the tooltip text
        $tooltip_text = esc_attr(strip_tags($tooltip_text)); // Ensure tooltip text is safe
        $link = get_permalink($term->ID);
        $tooltip = '<span class="glossary-tooltip" data-tooltip="' . esc_js($tooltip_text) . '">' . esc_html($term_title) . '</span>';  // Use data-tooltip
        $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/';
        $replacement = '<a href="' . esc_url($link) . '" target="_blank">' . $tooltip . '</a>';
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}
add_filter('the_content', 'glossary_tooltip_filter');

// Add Glossary Info Page to Admin Menu
function glossary_add_submenu_pages() {
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

// Callback function for the Info page
function glossary_info_page_callback() {
    $glossary_count = wp_count_posts('glossary')->publish;
    echo '<div class="wrap">';
    echo esc_html__('Glossary Info', 'text_domain');
    echo '<p>' . esc_html__('To use the Glossary plugin, insert the following shortcode on an archive page:', 'text_domain') . '</p>';
    echo '<code>[glossary_archive]</code>';
    echo '<p>' . sprintf(esc_html__('There are currently %s glossary terms available.', 'text_domain'), $glossary_count) . '</p>';

    // Made with love <3
    echo '<p>Created by ChatGPT & <a href="https://www.nuvorix.com" target="_blank">www.Nuvorix.com</a> with love ❤️.</p>';
    echo '<p><a href="https://github.com/Nuvorix/glossary-plugin" target="_blank">GitHub</a></p>';
    echo '</div>';
}
