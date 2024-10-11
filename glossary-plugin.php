<?php
/*
Plugin Name: Glossary Plugin Fixed Tooltip with Cache
Description: A custom glossary plugin with tooltip, archive functionality, caching, and improved features.
Version: 1.9
Author: ChatGPT & Nuvorix.com
*/

// Register Custom Post Type with Metabox for Tooltip and Abbreviation Full Form
function create_glossary_post_type() {
    $labels = array(
        'name'                  => _x( 'Glossary', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Term', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Glossary', 'text_domain' ),
        'name_admin_bar'        => __( 'Glossary Term', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'add_new_item'          => __( 'Add New Glossary Term', 'text_domain' ),
        'new_item'              => __( 'New Glossary Term', 'text_domain' ),
        'edit_item'             => __( 'Edit Glossary Term', 'text_domain' ),
        'view_item'             => __( 'View Glossary Term', 'text_domain' ),
        'all_items'             => __( 'All Glossary Terms', 'text_domain' ),
        'search_items'          => __( 'Search Glossary Terms', 'text_domain' ),
        'not_found'             => __( 'No Glossary Terms found.', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Glossary', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor' ),
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'rewrite'               => array('slug' => 'glossary'),
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'glossary', $args );
}
add_action( 'init', 'create_glossary_post_type', 0 );

// Include additional files for glossary page and archive functionalities
include_once plugin_dir_path(__FILE__) . 'glossary-page.php';
include_once plugin_dir_path(__FILE__) . 'glossary-archive.php';

// Add Custom Metabox for Tooltip and Abbreviation Full Form
function glossary_add_meta_box() {
    add_meta_box(
        'glossary_tooltip_text',
        __( 'Tooltip Text (300 characters)', 'text_domain' ),
        'glossary_meta_box_callback',
        'glossary',
        'normal',
        'high'
    );
    add_meta_box(
        'glossary_abbreviation_full_form',
        __( 'Abbreviation Full Form', 'text_domain' ),
        'glossary_abbreviation_meta_box_callback',
        'glossary',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'glossary_add_meta_box' );

function glossary_meta_box_callback( $post ) {
    wp_nonce_field( 'save_tooltip_text', 'glossary_tooltip_nonce' );
    $value = get_post_meta( $post->ID, '_tooltip_text', true );
    echo '<textarea style="width:100%;height:100px;" id="glossary_tooltip_text" name="glossary_tooltip_text" maxlength="300">' . esc_textarea( $value ) . '</textarea>';
}

function glossary_abbreviation_meta_box_callback( $post ) {
    wp_nonce_field( 'save_abbreviation_full_form', 'glossary_abbreviation_nonce' );
    $value = get_post_meta( $post->ID, '_abbreviation_full_form', true );
    echo '<input type="text" style="width:100%;" id="glossary_abbreviation_full_form" name="glossary_abbreviation_full_form" value="' . esc_attr( $value ) . '">';
}

// Save Abbreviation Full Form and Tooltip Text
function glossary_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['glossary_tooltip_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['glossary_tooltip_nonce'], 'save_tooltip_text' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( isset( $_POST['glossary_tooltip_text'] ) ) {
        $tooltip_text = wp_kses_post( $_POST['glossary_tooltip_text'] );
        update_post_meta( $post_id, '_tooltip_text', $tooltip_text );
    }

    if ( ! isset( $_POST['glossary_abbreviation_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['glossary_abbreviation_nonce'], 'save_abbreviation_full_form' ) ) {
        return;
    }
    if ( isset( $_POST['glossary_abbreviation_full_form'] ) ) {
        $abbreviation_full_form = sanitize_text_field( $_POST['glossary_abbreviation_full_form'] );
        update_post_meta( $post_id, '_abbreviation_full_form', $abbreviation_full_form );
    }

    // Clear cache when a post is updated or created
    wp_cache_delete( 'glossary_terms_cache' );
}
add_action( 'save_post', 'glossary_save_meta_box_data' );

// Clear cache when glossary post is updated
function clear_glossary_term_cache( $post_id ) {
    if ( get_post_type( $post_id ) == 'glossary' ) {
        wp_cache_delete( 'glossary_terms_cache' );
    }
}
add_action( 'save_post', 'clear_glossary_term_cache' );

// Add Tooltip Functionality with Improved Regex (case-sensitive)
function glossary_tooltip_filter( $content ) {
    if ( is_single() || is_page() ) {
        $terms = get_posts( array(
            'post_type' => 'glossary',
            'posts_per_page' => -1
        ) );

        foreach ( $terms as $term ) {
            $term_title = $term->post_title;
            $tooltip_text = get_post_meta( $term->ID, '_tooltip_text', true );

            // Ensure tooltip_text is not null or empty
            if ( empty($tooltip_text) ) {
                $tooltip_text = wp_strip_all_tags( $term->post_excerpt );
            }

            if ( empty($tooltip_text) ) {
                $tooltip_text = 'No description available';
            } else {
                // Ensure no HTML tags inside the tooltip
                $tooltip_text = esc_attr( strip_tags( $tooltip_text ) );
            }

            $link = get_permalink( $term->ID );
            $tooltip = '<span class="glossary-tooltip" title="' . esc_attr($tooltip_text) . '">' . esc_html( $term_title ) . '</span>';

            // Regex to replace terms, but exclude replacements inside tooltips
            $pattern = '/(?<!\w)(' . preg_quote($term_title, '/') . ')(?!\w)(?![^<]*>)/'; // Exclude terms inside HTML tags
            $replacement = '<a href="' . esc_url( $link ) . '" target="_blank">' . $tooltip . '</a>';
            $content = preg_replace($pattern, $replacement, $content);
        }
    }
    return $content;
}
add_filter( 'the_content', 'glossary_tooltip_filter' );


// Enqueue Styles and JavaScript for handling tooltip title and hiding navigation
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
        border: 1px solid rgba(201, 192, 22, 0.75);
        border-radius: 8px;
        padding: 10px;
        position: absolute;
        z-index: 1000;
        white-space: normal;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        max-width: 300px;
        line-height: 1.5;
    }
    .post-navigation {
        display: none !important;
    }
    </style>';

    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const glossaryTooltips = document.querySelectorAll(".glossary-tooltip");

        glossaryTooltips.forEach(function(tooltip) {
            tooltip.addEventListener("mouseenter", function() {
                tooltip.setAttribute("data-tooltip", tooltip.getAttribute("title"));
                tooltip.removeAttribute("title");
            });

            tooltip.addEventListener("mouseleave", function() {
                tooltip.setAttribute("title", tooltip.getAttribute("data-tooltip"));
                tooltip.removeAttribute("data-tooltip");
            });
        });
    });
    </script>';
}
add_action( 'wp_head', 'glossary_enqueue_assets' );
?>
