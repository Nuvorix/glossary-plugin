<?php
/*
Plugin Name: Glossary Archive Page
Description: A custom archive page for glossary terms with search functionality and alphabetical filtering, optimized for performance and security.
Version: 1.0 (Stable Release)
Requires at least: 5.0
Tested up to: 6.6.2
Author: ChatGPT & Nuvorix.com
*/

// Shortcode for Glossary Archive Page
function glossary_archive_shortcode() {
    try {
        ob_start();

        // Alphabet letters for navigation, including "All"
        $alphabet = array_merge(array('All'), range('A', 'Z'));
        $current_letter = isset($_GET['letter']) ? strtoupper(sanitize_text_field($_GET['letter'])) : '';
        $search_query = isset($_GET['glossary_search']) ? sanitize_text_field($_GET['glossary_search']) : '';
        $paged = max(1, absint(get_query_var('paged', 1)));

        // Display the alphabet filter
        echo '<div class="glossary-alphabet">';
        foreach ($alphabet as $letter) {
            $active = ($letter === $current_letter || ($current_letter === '' && $letter === 'All')) ? 'active' : '';
            $letter_url = ($letter === 'All') ? remove_query_arg(array('letter', 'glossary_search')) : add_query_arg(array('letter' => $letter, 'glossary_search' => null));
            echo '<a class="glossary-letter ' . esc_attr($active) . '" href="' . esc_url($letter_url) . '">' . esc_html($letter) . '</a> ';
        }
        echo '</div>';

        // Display search form
        echo '<form method="get" class="glossary-search-form" action="">';
        echo '<input type="text" name="glossary_search" placeholder="Search for terms..." value="' . esc_attr($search_query) . '">';
        echo '<button type="submit">Search</button>';
        echo '</form>';

        // Query arguments
        $args = array(
            'post_type' => 'glossary',
            'posts_per_page' => 25, // Limit to 25 terms per page to reduce load
            'paged' => $paged,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        // Add filters for search and letter-based filtering
        add_filter('posts_where', 'glossary_search_filter', 10, 2);
        add_filter('posts_where', 'glossary_letter_filter', 10, 2);

        // Execute the query
        $glossary_terms = new WP_Query($args);

        if ($glossary_terms->have_posts()) {
            echo '<ul class="glossary-list">';
            while ($glossary_terms->have_posts()) {
                $glossary_terms->the_post();
                $abbreviation_full_form = get_post_meta(get_the_ID(), '_abbreviation_full_form', true);
                echo '<li><a href="' . esc_url(get_permalink()) . '"><strong class="glossary-term-title">' . esc_html(get_the_title()) . '</strong>';
                if (!empty($abbreviation_full_form)) {
                    echo ' <span class="glossary-term-abbreviation">(' . esc_html($abbreviation_full_form) . ')</span>';
                }
                echo '</a></li>';
            }
            echo '</ul>';

            // Display pagination links
            echo paginate_links(array(
                'total' => $glossary_terms->max_num_pages,
                'current' => $paged,
            ));

            // Display a message if there are more terms available
            if ($glossary_terms->max_num_pages > 1) {
                echo '<p>There are more terms available. Please use the search feature to find a specific word or term.</p>';
            }
        } else {
            echo '<p>No terms found.</p>';
        }

        wp_reset_postdata();

        // Remove custom filters after use
        remove_filter('posts_where', 'glossary_search_filter', 10, 2);
        remove_filter('posts_where', 'glossary_letter_filter', 10, 2);

        return ob_get_clean();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return '<p>Error loading glossary terms. Please try again later.</p>';
    }
}
add_shortcode('glossary_archive', 'glossary_archive_shortcode');

// Add external CSS for glossary archive page
function glossary_archive_styles() {
    wp_enqueue_style('glossary-archive-style', plugin_dir_url(__FILE__) . 'css/glossary-archive.css');
}
add_action('wp_enqueue_scripts', 'glossary_archive_styles');

// Filter functions for search and letter filtering
function glossary_search_filter($where, $wp_query) {
    global $wpdb;
    $search_query = isset($_GET['glossary_search']) ? sanitize_text_field($_GET['glossary_search']) : '';
    if (!empty($search_query)) {
        $where .= $wpdb->prepare(" AND ($wpdb->posts.post_title LIKE %s OR EXISTS (SELECT 1 FROM $wpdb->postmeta WHERE $wpdb->postmeta.post_id = $wpdb->posts.ID AND $wpdb->postmeta.meta_key = '_abbreviation_full_form' AND $wpdb->postmeta.meta_value LIKE %s))", '%' . $wpdb->esc_like($search_query) . '%', '%' . $wpdb->esc_like($search_query) . '%');
    }
    return $where;
}

function glossary_letter_filter($where, $wp_query) {
    global $wpdb;
    $current_letter = isset($_GET['letter']) ? strtoupper(sanitize_text_field($_GET['letter'])) : '';
    if ($current_letter && $current_letter !== 'All' && empty($_GET['glossary_search'])) {
        $where .= $wpdb->prepare(" AND $wpdb->posts.post_title LIKE %s", $current_letter . '%');
    }
    return $where;
}
