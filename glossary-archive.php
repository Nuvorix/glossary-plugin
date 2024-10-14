<?php
/*
Plugin Name: Glossary Archive Page Optimized
Description: A custom archive page for glossary terms with search functionality and alphabetical filtering, optimized for performance and security.
Version: 3.2
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

        // Cache key for the glossary terms
        $cache_key = 'cached_glossary_terms_' . md5($current_letter . $search_query . $paged);
        $cached_glossary_terms = get_transient($cache_key);

        if ($cached_glossary_terms === false) {
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
                $cached_glossary_terms = [];
                while ($glossary_terms->have_posts()) {
                    $glossary_terms->the_post();
                    $abbreviation_full_form = get_post_meta(get_the_ID(), '_abbreviation_full_form', true);
                    $cached_glossary_terms[] = array(
                        'title' => get_the_title(),
                        'link' => get_permalink(),
                        'abbreviation' => $abbreviation_full_form,
                    );
                }
                // Save the glossary terms to the transient with 12 hours expiration
                set_transient($cache_key, $cached_glossary_terms, 12 * HOUR_IN_SECONDS);
            }
            wp_reset_postdata();

            // Remove custom filters after use
            remove_filter('posts_where', 'glossary_search_filter', 10, 2);
            remove_filter('posts_where', 'glossary_letter_filter', 10, 2);
        }

        // Display glossary terms from cache or live query
        if (!empty($cached_glossary_terms)) {
            echo '<ul class="glossary-list">';
            foreach ($cached_glossary_terms as $term) {
                echo '<li><a href="' . esc_url($term['link']) . '"><strong style="color: rgba(238, 238, 34, 0.75); font-size: 0.9em;">' . esc_html($term['title']) . '</strong>';
                if (!empty($term['abbreviation'])) {
                    echo ' <span style="font-weight: normal; font-size: 0.8em;">(' . esc_html($term['abbreviation']) . ')</span>';
                }
                echo '</a></li>';
            }
            echo '</ul>';

            // Display pagination links
            echo paginate_links(array(
                'total' => $glossary_terms->max_num_pages ?? 1,
                'current' => $paged,
            ));

            // Display a message if there are more terms available
            if ($glossary_terms->max_num_pages > 1) {
                echo '<p>There are more terms available. Please use the search feature to find a specific word or term.</p>';
            }
        } else {
            echo '<p>No terms found.</p>';
        }

        return ob_get_clean();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return '<p>Error loading glossary terms. Please try again later.</p>';
    }
}
add_shortcode('glossary_archive', 'glossary_archive_shortcode');

// Add styles for glossary archive page and hide post navigation
function glossary_archive_styles() {
    if (is_page_template('glossary-archive.php') || is_singular('glossary')) {
        echo '<style>
        .post-navigation, .nav-links {
            display: none !important;
        }
        </style>';
    }

    echo '<style>
    .glossary-alphabet {
        margin-bottom: 20px;
        text-align: center; /* Center the alphabet */
        font-size: 20px; /* Make the text larger */
    }
    .glossary-letter {
        margin: 0 5px;
        text-decoration: none;
        font-weight: bold;
        font-size: 22px;
        color: #0073aa;
        transition: color 0.3s ease;
    }
    .glossary-letter.active {
        font-weight: bold;
        text-decoration: underline; /* Underline active letter */
    }
    .glossary-letter:hover {
        color: #333;
    }
    .glossary-search-form {
        text-align: center;
        margin-bottom: 20px;
    }
    .glossary-search-form input[type="text"] {
        width: 60%;
        padding: 10px;
        margin-right: 10px;
    }
    .glossary-search-form button {
        padding: 10px 20px;
    }
    .glossary-list {
        list-style: none;
        padding: 0;
        text-align: left;
    }
    .glossary-list li {
        margin: 5px 0;
    }
    .glossary-list a {
        text-decoration: none;
        font-size: 20px;
        color: inherit;
        transition: color 0.3s ease;
    }
    .glossary-list a:hover {
        color: #0073aa;
    }
    </style>';
}
add_action('wp_head', 'glossary_archive_styles');

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

// Delete transient cache on post save or delete
function delete_glossary_transient_cache($post_id) {
    if (get_post_type($post_id) === 'glossary') {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_cached_glossary_terms_%'");
    }
}
add_action('save_post', 'delete_glossary_transient_cache');
add_action('delete_post', 'delete_glossary_transient_cache');

// Completely remove post navigation for glossary custom post type
function remove_glossary_post_navigation() {
    if (is_singular('glossary')) {
        remove_action('wp_footer', 'the_post_navigation');
    }
}
add_action('wp', 'remove_glossary_post_navigation');
?>
